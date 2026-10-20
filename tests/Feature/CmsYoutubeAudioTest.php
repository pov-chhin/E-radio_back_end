<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Src\Catalog\Jobs\FetchYoutubeAudio;
use Src\Catalog\Jobs\ProcessMediaContentAudio;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\OpusAudioProcessor;
use Src\Catalog\Services\YoutubeAudioFetcher;
use Src\Setting\Models\StorageSetting;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * An episode whose audio is a YouTube link: the server fetches the audio (yt-dlp), shrinks it with FFmpeg to a
 * 24 kbps mono Opus file and the app plays that — audio only. yt-dlp itself is faked here (no network).
 */
class CmsYoutubeAudioTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const LINK = 'https://youtu.be/D5-Y6_JABoI?si=O-_C8q7r8HQ0jqT3';

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Queue::fake();
        $this->actingAs($this->createAdmin(['view-media-content', 'create-media-content', 'edit-media-content', 'delete-media-content']), 'admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function category(): Category
    {
        return Category::where('slug', 'podcast')->firstOrFail();
    }

    private function episode(array $attributes = []): MediaContent
    {
        return MediaContent::create([
            'category_id' => $this->category()->id,
            'title' => 'Pilot', 'slug' => 'pilot', 'media_type' => 'audio',
            ...$attributes,
        ]);
    }

    /** A one-second stereo WAV, standing in for what yt-dlp downloads. */
    private function audioFixture(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'yt_fixture_');
        @unlink($base);
        $path = $base.'.wav';
        $this->temporaryFiles[] = $path;

        (new Process([
            config('services.ffmpeg.binary', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-ac', '2',
            $path,
        ]))->mustRun();

        return $path;
    }

    private function fetcherReturning(string $fixture): YoutubeAudioFetcher
    {
        return new class($fixture) extends YoutubeAudioFetcher
        {
            public function __construct(private readonly string $fixture) {}

            public function download(string $url): string
            {
                $copy = tempnam(sys_get_temp_dir(), 'yt_download_');
                copy($this->fixture, $copy);

                return $copy;
            }
        };
    }

    private function fetcherFailing(string $message): YoutubeAudioFetcher
    {
        return new class($message) extends YoutubeAudioFetcher
        {
            public function __construct(private readonly string $message) {}

            public function download(string $url): string
            {
                throw new RuntimeException($this->message);
            }
        };
    }

    private function runJob(MediaContent $mediaContent, YoutubeAudioFetcher $fetcher, ?string $url = null): void
    {
        (new FetchYoutubeAudio($mediaContent->id, $url ?? self::LINK))
            ->handle($fetcher, app(OpusAudioProcessor::class));
    }

    private function probe(string $path, string $entries): string
    {
        $process = new Process([
            config('services.ffmpeg.probe_binary', 'ffprobe'),
            '-v', 'error', '-show_entries', $entries, '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    #[DataProvider('links')]
    public function test_the_video_id_is_read_from_every_kind_of_youtube_link(string $url, ?string $id): void
    {
        $this->assertSame($id, YoutubeAudioFetcher::videoId($url));
    }

    public static function links(): array
    {
        return [
            'short link with tracking' => [self::LINK, 'D5-Y6_JABoI'],
            'watch page with a playlist' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL123', 'dQw4w9WgXcQ'],
            'music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'a lookalike host' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ', null],
            'a channel' => ['https://www.youtube.com/@somechannel', null],
            'a short id' => ['https://youtu.be/abc', null],
            'a plain mp3' => ['https://cdn.example.com/pilot.mp3', null],
            'not a web address' => ['javascript:alert(1)', null],
        ];
    }

    #[DataProvider('errors')]
    public function test_only_a_refused_or_dropped_request_is_worth_trying_again(string $error, bool $retry): void
    {
        $this->assertSame($retry, YoutubeAudioFetcher::isTransient($error));
    }

    public static function errors(): array
    {
        return [
            'a refused download' => ['ERROR: unable to download video data: HTTP Error 403: Forbidden', true],
            'rate limited' => ['ERROR: unable to download video data: HTTP Error 429: Too Many Requests', true],
            'a server error' => ['ERROR: HTTP Error 503: Service Unavailable', true],
            'a timeout' => ['ERROR: The read operation timed out', true],
            'an unavailable video' => ['ERROR: [youtube] abc: This video is unavailable', false],
            'a private video' => ["ERROR: [youtube] abc: Private video. Sign in if you've been granted access", false],
            'a bot check' => ["ERROR: [youtube] abc: Sign in to confirm you're not a bot", false],
            'nothing' => ['', false],
        ];
    }

    public function test_a_pasted_youtube_link_is_fetched_in_the_background_and_not_stored_as_the_stream(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'Pilot', 'slug' => 'pilot', 'media_type' => 'audio', 'status' => 'public',
            'stream_url' => self::LINK,
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'pilot')->firstOrFail();

        $this->assertNull($mediaContent->stream);
        $this->assertSame(self::LINK, $mediaContent->source_url);
        $this->assertSame('processing', $mediaContent->processing_status);
        // Not public until the file is ready, so the app never receives a YouTube address.
        $this->assertFalse(MediaContent::publiclyVisible()->whereKey($mediaContent->id)->exists());
        Queue::assertPushed(FetchYoutubeAudio::class, fn ($job) => $job->mediaContentId === $mediaContent->id && $job->url === self::LINK);
    }

    public function test_the_worker_turns_the_video_into_a_small_mono_opus_file_and_keeps_the_link(): void
    {
        $mediaContent = $this->episode(['source_url' => self::LINK, 'processing_status' => 'processing']);

        $this->runJob($mediaContent, $this->fetcherReturning($this->audioFixture()));

        $mediaContent->refresh();
        $this->assertSame('ready', $mediaContent->processing_status);
        $this->assertNull($mediaContent->processing_error);
        $this->assertSame(self::LINK, $mediaContent->source_url);
        $this->assertStringEndsWith('.opus', $mediaContent->stream);
        Storage::disk('public')->assertExists($mediaContent->stream);
        $this->assertStringStartsWith('OggS', Storage::disk('public')->get($mediaContent->stream));
        $this->assertSame(1, $mediaContent->duration_seconds);

        $file = Storage::disk('public')->path($mediaContent->stream);
        $this->assertSame('1', $this->probe($file, 'stream=channels'));
        // About 24 kbps plus the container's own overhead — far from the 64 kbps stereo an upload gets.
        $bitsPerSecond = Storage::disk('public')->size($mediaContent->stream) * 8 / (float) $this->probe($file, 'format=duration');
        $this->assertLessThan(48_000, $bitsPerSecond);
    }

    /** A fetcher that hands over `$contents` as the downloaded video and remembers where it put it. */
    private function recordingFetcher(string $contents): YoutubeAudioFetcher
    {
        return new class($contents) extends YoutubeAudioFetcher
        {
            public ?string $downloaded = null;

            public function __construct(private readonly string $contents) {}

            public function download(string $url): string
            {
                $this->downloaded = tempnam(sys_get_temp_dir(), 'yt_download_');
                file_put_contents($this->downloaded, $this->contents);

                return $this->downloaded;
            }
        };
    }

    public function test_the_downloaded_video_is_deleted_once_it_is_converted_to_opus(): void
    {
        $mediaContent = $this->episode(['source_url' => self::LINK, 'processing_status' => 'processing']);
        $fetcher = $this->recordingFetcher((string) file_get_contents($this->audioFixture()));

        $this->runJob($mediaContent, $fetcher);

        $this->assertSame('ready', $mediaContent->fresh()->processing_status);
        $this->assertNotNull($fetcher->downloaded);
        $this->assertFileDoesNotExist($fetcher->downloaded);
    }

    public function test_the_downloaded_video_is_deleted_even_when_the_conversion_fails(): void
    {
        $mediaContent = $this->episode(['source_url' => self::LINK, 'processing_status' => 'processing']);
        $fetcher = $this->recordingFetcher('this is not audio');

        try {
            $this->runJob($mediaContent, $fetcher);
            $this->fail('The conversion of a file that is not audio should have failed.');
        } catch (RuntimeException) {
            // Expected: the failure is recorded on the episode and rethrown for the queue.
        }

        $this->assertSame('failed', $mediaContent->fresh()->processing_status);
        $this->assertNotNull($fetcher->downloaded);
        $this->assertFileDoesNotExist($fetcher->downloaded);
    }

    public function test_the_fetcher_prepares_encrypted_admin_cookies_as_a_private_temporary_file(): void
    {
        $cookies = "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t2147483647\tSID\tsecret\n";
        StorageSetting::firstOrFail()->update(['youtube_cookies' => $cookies]);
        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'cookiesFile');

        [$path, $temporary] = $method->invoke($fetcher);
        $this->temporaryFiles[] = $temporary;

        $this->assertSame($temporary, $path);
        $this->assertSame($cookies, file_get_contents($path));
    }

    public function test_an_empty_configured_cookie_file_is_rejected_before_yt_dlp_runs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yt_empty_cookies_');
        $this->temporaryFiles[] = $path;
        config(['services.yt_dlp.cookies' => $path]);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'cookiesFile');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid or empty');
        $method->invoke($fetcher);
    }

    public function test_a_valid_configured_cookie_file_is_passed_to_yt_dlp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yt_valid_cookies_');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t2147483647\tSID\tsecret\n");
        config(['services.yt_dlp.cookies' => $path]);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'cookiesFile');

        $this->assertSame([$path, null], $method->invoke($fetcher));
    }

    public function test_visitor_data_is_configured_separately_from_browser_cookies(): void
    {
        config([
            'services.yt_dlp.cookies' => null,
            'services.yt_dlp.cookies_from_browser' => null,
            'services.yt_dlp.visitor_data' => 'visitor-data-value',
        ]);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'authenticationArguments');

        $this->assertSame([
            '--extractor-args', 'youtubetab:skip=webpage',
            '--extractor-args', 'youtube:player_skip=webpage,configs;visitor_data=visitor-data-value',
        ], $method->invoke($fetcher, null));
        $this->assertSame(['--cookies', 'cookies.txt'], $method->invoke($fetcher, 'cookies.txt'));
    }

    public function test_an_enabled_browser_profile_takes_precedence_over_cookie_files(): void
    {
        config([
            'services.yt_dlp.cookies_from_browser' => 'edge',
            'services.yt_dlp.visitor_data' => 'visitor-data-value',
        ]);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'authenticationArguments');

        $this->assertSame(['--cookies-from-browser', 'edge'], $method->invoke($fetcher, 'cookies.txt'));
        $this->assertSame(['--cookies', 'cookies.txt'], $method->invoke($fetcher, 'cookies.txt', true));
    }

    /** The reason an admin sees when yt-dlp is refused with YouTube's "sign in to confirm you're not a bot". */
    private function botCheckMessage(bool $signedIn, bool $proxied): string
    {
        $process = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "ERROR: [youtube] abc: Sign in to confirm you’re not a bot. Use --cookies\n"); exit(1);']);
        $process->run();

        $fetcher = new YoutubeAudioFetcher;

        return (new \ReflectionMethod($fetcher, 'failureMessage'))->invoke($fetcher, $process, $signedIn, $proxied);
    }

    public function test_the_bot_check_message_says_what_was_tried_instead_of_always_asking_for_cookies(): void
    {
        $plain = $this->botCheckMessage(signedIn: false, proxied: false);
        $this->assertStringContainsString('Upload a fresh Netscape cookies.txt', $plain);
        $this->assertStringContainsString('YT_DLP_PROXY', $plain);

        // With cookies already saved, "upload cookies" would be wrong advice: it is the server's address.
        $withCookies = $this->botCheckMessage(signedIn: true, proxied: false);
        $this->assertStringContainsString('even with the saved cookies', $withCookies);
        $this->assertStringContainsString('datacenter', $withCookies);
        $this->assertStringNotContainsString('Upload a fresh Netscape cookies.txt', $withCookies);

        $proxied = $this->botCheckMessage(signedIn: true, proxied: true);
        $this->assertStringContainsString('even through the proxy', $proxied);
        $this->assertStringContainsString('residential', $proxied);
    }

    public function test_an_extractor_error_points_at_an_out_of_date_yt_dlp_instead_of_asking_to_report_it(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "WARNING: [youtube] Unable to download webpage: HTTP Error 403: Forbidden\nERROR: [youtube] QvNjy95J4A4: An extractor error has occurred. (caused by KeyError(\'INNERTUBE_CONTEXT\')); please report this issue on  https://github.com/yt-dlp/yt-dlp/issues?q= , filling out the appropriate issue template.\n"); exit(1);']);
        $process->run();

        $fetcher = new YoutubeAudioFetcher;
        $message = (new \ReflectionMethod($fetcher, 'failureMessage'))->invoke($fetcher, $process);

        $this->assertStringContainsString("KeyError('INNERTUBE_CONTEXT')", $message);
        $this->assertStringContainsString('HTTP Error 403: Forbidden', $message);
        $this->assertStringContainsString('out of date', $message);
        $this->assertStringContainsString('redeploy', $message);
        $this->assertStringNotContainsString('please report', $message);
        $this->assertStringNotContainsString('github.com', $message);
    }

    public function test_a_configured_proxy_is_passed_to_yt_dlp_and_nothing_is_added_without_one(): void
    {
        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'proxyArguments');

        config(['services.yt_dlp.proxy' => null]);
        $this->assertSame([], $method->invoke($fetcher));

        config(['services.yt_dlp.proxy' => ' socks5h://user:pa55@127.0.0.1:1080 ']);
        $this->assertSame(['--proxy', 'socks5h://user:pa55@127.0.0.1:1080'], $method->invoke($fetcher));
    }

    public function test_a_proxy_setting_that_is_not_an_address_is_refused_with_a_clear_message(): void
    {
        config(['services.yt_dlp.proxy' => '127.0.0.1:1080']);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'proxyArguments');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YT_DLP_PROXY is not a proxy address');
        $method->invoke($fetcher);
    }

    public function test_the_proxy_address_and_its_password_never_reach_the_error_shown_to_admins(): void
    {
        config(['services.yt_dlp.proxy' => 'http://user:pa55@proxy.example.com:8080']);

        $fetcher = new YoutubeAudioFetcher;
        $method = new \ReflectionMethod($fetcher, 'withoutProxySecrets');

        $text = $method->invoke($fetcher, 'Unable to connect to proxy http://user:pa55@proxy.example.com:8080 (password pa55 rejected)');

        $this->assertSame('Unable to connect to proxy [proxy] (password [proxy] rejected)', $text);
        $this->assertStringNotContainsString('pa55', $text);
    }

    /**
     * A stand-in for the yt-dlp binary: it logs whether it was given --proxy, fails like a dead proxy when it was,
     * and otherwise either "downloads" a file or answers with YouTube's bot check.
     *
     * @return array{0: string, 1: string} [binary to configure, log of the calls]
     */
    private function fakeYtDlp(bool $directWorks): array
    {
        $script = tempnam(sys_get_temp_dir(), 'fake_ytdlp_');
        $log = $script.'.log';
        $php = PHP_BINARY;
        $wrapper = $script.(PHP_OS_FAMILY === 'Windows' ? '.bat' : '.sh');
        $this->temporaryFiles = [...$this->temporaryFiles, $script, $log, $wrapper];

        file_put_contents($script, str_replace('__DIRECT_WORKS__', $directWorks ? 'true' : 'false', <<<'PHP'
<?php
$proxied = in_array('--proxy', $argv, true);
file_put_contents(__FILE__.'.log', ($proxied ? 'proxy' : 'direct')."\n", FILE_APPEND);
if ($proxied) {
    fwrite(STDERR, "ERROR: [youtube] abc: Unable to download API page: ('Unable to connect to proxy', NewConnectionError(\"HTTPSConnection(host='proxy.example.com', port=3128): Failed to establish a new connection: [Errno 111] Connection refused\"))\n");
    exit(1);
}
if (! __DIRECT_WORKS__) {
    fwrite(STDERR, "ERROR: [youtube] abc: Sign in to confirm you're not a bot\n");
    exit(1);
}
// Windows' cmd mangles some arguments of a .bat wrapper (the "-o" path loses its drive), so find the name instead.
preg_match('/yt_audio_[0-9a-f-]{36}/', implode(' ', $argv), $name);
file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.$name[0].'.m4a', 'audio');
PHP));

        file_put_contents($wrapper, PHP_OS_FAMILY === 'Windows'
            ? "@echo off\r\n\"{$php}\" \"{$script}\" %*\r\n"
            : "#!/bin/sh\nexec '{$php}' '{$script}' \"\$@\"\n");
        chmod($wrapper, 0755);

        return [$wrapper, $log];
    }

    public function test_an_unreachable_proxy_is_skipped_and_youtube_is_tried_directly(): void
    {
        [$binary, $log] = $this->fakeYtDlp(directWorks: true);
        config([
            'services.yt_dlp.binary' => $binary,
            'services.yt_dlp.proxy' => 'http://proxy.example.com:3128',
            'services.yt_dlp.cookies' => null,
            'services.yt_dlp.cookies_from_browser' => null,
        ]);

        $file = (new YoutubeAudioFetcher)->download(self::LINK);
        $this->temporaryFiles[] = $file;

        $this->assertFileExists($file);
        $this->assertSame("proxy\ndirect\n", str_replace("\r\n", "\n", file_get_contents($log)));
    }

    public function test_when_the_direct_try_fails_too_the_admin_is_told_the_proxy_could_not_be_reached(): void
    {
        [$binary, $log] = $this->fakeYtDlp(directWorks: false);
        config([
            'services.yt_dlp.binary' => $binary,
            'services.yt_dlp.proxy' => 'http://proxy.example.com:3128',
            'services.yt_dlp.cookies' => null,
            'services.yt_dlp.cookies_from_browser' => null,
        ]);

        try {
            (new YoutubeAudioFetcher)->download(self::LINK);
            $this->fail('The download should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('asks this server to sign in', $exception->getMessage());
            $this->assertStringContainsString('could not be reached, so YouTube was tried directly', $exception->getMessage());
        }

        $this->assertSame("proxy\ndirect\n", str_replace("\r\n", "\n", file_get_contents($log)));
    }

    public function test_only_a_proxy_that_cannot_be_reached_is_skipped_not_a_youtube_refusal(): void
    {
        $this->assertTrue(YoutubeAudioFetcher::isProxyFailure("ERROR: [youtube] abc: Unable to download API page: ('Unable to connect to proxy', NewConnectionError(...Connection refused))"));
        $this->assertTrue(YoutubeAudioFetcher::isProxyFailure('ERROR: Unable to download webpage: ProxyError(...Tunnel connection failed: 407 Proxy Authentication Required)'));
        $this->assertFalse(YoutubeAudioFetcher::isProxyFailure('ERROR: unable to download video data: HTTP Error 403: Forbidden'));
        $this->assertFalse(YoutubeAudioFetcher::isProxyFailure("ERROR: [youtube] abc: Sign in to confirm you're not a bot"));
        $this->assertFalse(YoutubeAudioFetcher::isProxyFailure(''));
    }

    public function test_the_old_stream_stays_until_the_new_file_is_ready_and_is_then_deleted(): void
    {
        Storage::disk('public')->put('media-contents/streams/old.opus', 'old');
        $mediaContent = $this->episode([
            'stream' => 'media-contents/streams/old.opus', 'processing_status' => 'processing', 'source_url' => self::LINK,
        ]);

        $this->runJob($mediaContent, $this->fetcherReturning($this->audioFixture()));

        $mediaContent->refresh();
        $this->assertNotSame('media-contents/streams/old.opus', $mediaContent->stream);
        Storage::disk('public')->assertMissing('media-contents/streams/old.opus');
        Storage::disk('public')->assertExists($mediaContent->stream);
    }

    public function test_a_legacy_youtube_address_as_the_old_stream_is_simply_replaced(): void
    {
        $mediaContent = $this->episode(['stream' => self::LINK, 'processing_status' => 'processing', 'source_url' => self::LINK]);

        $this->runJob($mediaContent, $this->fetcherReturning($this->audioFixture()));

        $this->assertStringEndsWith('.opus', $mediaContent->fresh()->stream);
    }

    public function test_a_failed_fetch_is_recorded_with_its_reason_and_the_old_stream_is_kept(): void
    {
        Storage::disk('public')->put('media-contents/streams/old.opus', 'old');
        $mediaContent = $this->episode([
            'stream' => 'media-contents/streams/old.opus', 'processing_status' => 'processing', 'source_url' => self::LINK,
        ]);

        try {
            $this->runJob($mediaContent, $this->fetcherFailing('YouTube audio could not be fetched: This video is unavailable'));
            $this->fail('The failure should reach the queue so it is logged.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('This video is unavailable', $exception->getMessage());
        }

        $mediaContent->refresh();
        $this->assertSame('failed', $mediaContent->processing_status);
        $this->assertStringContainsString('This video is unavailable', $mediaContent->processing_error);
        $this->assertSame('media-contents/streams/old.opus', $mediaContent->stream);
        Storage::disk('public')->assertExists('media-contents/streams/old.opus');
    }

    public function test_a_job_for_a_link_that_was_since_changed_does_nothing(): void
    {
        $mediaContent = $this->episode(['source_url' => 'https://youtu.be/dQw4w9WgXcQ', 'processing_status' => 'processing']);

        // The job was queued for the first link; the admin has pasted another since.
        $this->runJob($mediaContent, $this->fetcherFailing('must not be called'), self::LINK);

        $mediaContent->refresh();
        $this->assertSame('processing', $mediaContent->processing_status);
        $this->assertNull($mediaContent->stream);
    }

    public function test_a_plain_link_afterwards_replaces_the_youtube_source(): void
    {
        $mediaContent = $this->episode(['source_url' => self::LINK, 'processing_status' => 'processing']);

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $mediaContent->category_id,
            'title' => 'Pilot', 'slug' => 'pilot', 'media_type' => 'audio', 'status' => 'private',
            'stream_url' => 'https://stream.example.com/pilot.mp3',
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame('https://stream.example.com/pilot.mp3', $mediaContent->stream);
        $this->assertNull($mediaContent->source_url);
        $this->assertSame('ready', $mediaContent->processing_status);
        Queue::assertNotPushed(FetchYoutubeAudio::class);
    }

    public function test_a_youtube_link_pasted_over_an_existing_file_keeps_it_playable_until_the_new_one_is_ready(): void
    {
        Storage::disk('public')->put('media-contents/streams/old.opus', 'old');
        $mediaContent = $this->episode(['stream' => 'media-contents/streams/old.opus', 'processing_status' => 'ready']);

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $mediaContent->category_id,
            'title' => 'Pilot', 'slug' => 'pilot', 'media_type' => 'audio', 'status' => 'private',
            'stream_url' => self::LINK,
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame('processing', $mediaContent->processing_status);
        $this->assertSame('media-contents/streams/old.opus', $mediaContent->stream);
        $this->assertSame(self::LINK, $mediaContent->source_url);
        Storage::disk('public')->assertExists('media-contents/streams/old.opus');
        Queue::assertPushed(FetchYoutubeAudio::class);
    }

    public function test_the_edit_page_says_where_the_audio_came_from_and_how_to_retry_a_failure(): void
    {
        $mediaContent = $this->episode(['source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'This video is unavailable']);

        $this->get("/admin/media-contents/{$mediaContent->id}/edit")
            ->assertOk()
            ->assertSee('Audio fetched from YouTube')
            ->assertSee('YouTube audio could not be fetched — paste the link again to retry');
    }

    public function test_the_command_queues_old_youtube_links_and_failed_fetches_only(): void
    {
        $legacy = $this->episode(['slug' => 'legacy', 'stream' => self::LINK, 'processing_status' => 'ready']);
        $failed = $this->episode(['slug' => 'failed', 'source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'x']);
        $mp3 = $this->episode(['slug' => 'mp3', 'stream' => 'https://cdn.example.com/a.mp3', 'processing_status' => 'ready']);
        $done = $this->episode(['slug' => 'done', 'stream' => 'media-contents/streams/a.opus', 'source_url' => self::LINK, 'processing_status' => 'ready']);

        $this->artisan('media:fetch-youtube-audio', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('ready', $legacy->fresh()->processing_status);
        Queue::assertNothingPushed();

        $this->artisan('media:fetch-youtube-audio')->assertSuccessful();

        foreach ([$legacy, $failed] as $episode) {
            $episode->refresh();
            $this->assertSame('processing', $episode->processing_status);
            $this->assertSame(self::LINK, $episode->source_url);
        }
        $this->assertSame('ready', $mp3->fresh()->processing_status);
        $this->assertSame('ready', $done->fresh()->processing_status);
        Queue::assertPushed(FetchYoutubeAudio::class, 2);
    }

    public function test_a_failed_youtube_fetch_is_retried_without_pasting_the_link_again(): void
    {
        $mediaContent = $this->episode([
            'source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot',
        ]);

        $this->post("/admin/media-contents/{$mediaContent->id}/retry-audio")
            ->assertRedirect()
            ->assertSessionHas('status');

        $mediaContent->refresh();
        $this->assertSame('processing', $mediaContent->processing_status);
        $this->assertNull($mediaContent->processing_error);
        Queue::assertPushed(FetchYoutubeAudio::class, fn ($job) => $job->mediaContentId === $mediaContent->id && $job->url === self::LINK);
    }

    public function test_retry_asked_for_by_the_page_answers_json_so_nothing_reloads(): void
    {
        $failed = $this->episode(['source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot']);

        $this->postJson("/admin/media-contents/{$failed->id}/retry-audio")
            ->assertOk()
            ->assertExactJson(['started' => true, 'message' => 'Trying again — the audio is being fetched.']);

        $this->assertSame('processing', $failed->fresh()->processing_status);
        Queue::assertPushed(FetchYoutubeAudio::class, 1);

        // Asked again (a double click, a stale page): nothing is queued twice, and the page is told so.
        $this->postJson("/admin/media-contents/{$failed->id}/retry-audio")
            ->assertStatus(409)
            ->assertJsonPath('started', false);
        Queue::assertPushed(FetchYoutubeAudio::class, 1);
    }

    public function test_the_retry_forms_and_badges_carry_what_the_pages_script_needs(): void
    {
        $song = Category::where('slug', 'song')->firstOrFail()->id;
        $failed = $this->episode(['slug' => 'failed', 'category_id' => $song, 'source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot']);

        $edit = $this->get("/admin/media-contents/{$failed->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('data-retry-audio', $edit);
        $this->assertStringContainsString('data-status-badge', $edit);
        $this->assertStringContainsString('data-retry-reason', $edit);

        $list = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('data-retry-audio', $list);
        $this->assertStringContainsString('data-status-badge', $list);

        // One script for every page, in the shared layout: it turns the Failed badge into Processing in place.
        $this->assertStringContainsString("form.matches('[data-retry-audio]')", $list);
    }

    public function test_the_status_address_reports_how_the_audio_is_doing(): void
    {
        $episode = $this->episode(['source_url' => self::LINK, 'processing_status' => 'processing']);
        $url = "/admin/media-contents/{$episode->id}/processing-status";

        $this->getJson($url)->assertOk()->assertExactJson(['status' => 'processing', 'error' => null]);

        $episode->update(['processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot']);
        $this->getJson($url)->assertOk()->assertExactJson(['status' => 'failed', 'error' => 'Sign in to confirm you are not a bot']);

        $episode->update(['processing_error' => str_repeat('x', 900)]);
        $cut = $this->getJson($url)->json('error');
        $this->assertSame(str_repeat('x', 300).'...', $cut);

        // Ready never carries an old reason along.
        $episode->update(['processing_status' => 'ready', 'processing_error' => 'left over']);
        $this->getJson($url)->assertOk()->assertExactJson(['status' => 'ready', 'error' => null]);
    }

    public function test_the_status_address_needs_permission_to_view_media_content(): void
    {
        $episode = $this->episode(['processing_status' => 'processing']);

        $this->actingAs($this->createAdmin(), 'admin');
        $this->getJson("/admin/media-contents/{$episode->id}/processing-status")->assertForbidden();
    }

    public function test_a_processing_row_is_marked_for_watching_and_keeps_its_retry_hidden_until_it_fails(): void
    {
        $song = Category::where('slug', 'song')->firstOrFail()->id;
        $processing = $this->episode(['slug' => 'busy', 'category_id' => $song, 'source_url' => self::LINK, 'processing_status' => 'processing']);

        $list = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('data-state="processing"', $list);
        $this->assertStringContainsString("/admin/media-contents/{$processing->id}/processing-status", $list);
        // The Retry form is there already (so a failure can show it) but out of sight.
        $this->assertMatchesRegularExpression('#retry-audio"[^>]*data-retry-audio[^>]*style="display:none"#', $list);

        $edit = $this->get("/admin/media-contents/{$processing->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('data-state="processing"', $edit);
        $this->assertStringContainsString('id="retry-audio-form"', $edit);
        $this->assertMatchesRegularExpression('#data-retry-button style="align-self:flex-start;display:none"#', $edit);

        $processing->update(['processing_status' => 'failed', 'processing_error' => 'x']);
        $failedList = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('data-state="failed"', $failedList);
        $this->assertDoesNotMatchRegularExpression('#data-retry-audio[^>]*style="display:none"#', $failedList);
    }

    public function test_retry_only_applies_to_a_failed_episode(): void
    {
        $ready = $this->episode(['slug' => 'ready', 'source_url' => self::LINK, 'stream' => 'media-contents/streams/a.opus', 'processing_status' => 'ready']);
        $busy = $this->episode(['slug' => 'busy', 'source_url' => self::LINK, 'processing_status' => 'processing']);

        foreach ([$ready, $busy] as $episode) {
            $this->post("/admin/media-contents/{$episode->id}/retry-audio")->assertRedirect();
        }

        $this->assertSame('ready', $ready->fresh()->processing_status);
        $this->assertSame('processing', $busy->fresh()->processing_status);
        Queue::assertNothingPushed();
    }

    public function test_a_failed_upload_is_retried_from_the_source_file_that_was_kept(): void
    {
        Storage::disk('public')->put('media-contents/uploads/kept.wav', 'RIFF');
        $mediaContent = $this->episode([
            'source_path' => 'media-contents/uploads/kept.wav', 'processing_status' => 'failed', 'processing_error' => 'FFmpeg failed',
        ]);

        $this->post("/admin/media-contents/{$mediaContent->id}/retry-audio")->assertRedirect();

        $this->assertSame('processing', $mediaContent->fresh()->processing_status);
        Queue::assertPushed(ProcessMediaContentAudio::class, fn ($job) => $job->mediaContentId === $mediaContent->id
            && $job->sourcePath === 'media-contents/uploads/kept.wav');
    }

    public function test_a_failure_with_nothing_left_to_retry_says_so_and_stays_failed(): void
    {
        // An upload whose source file is gone, and a failure with no source at all.
        $gone = $this->episode(['slug' => 'gone', 'source_path' => 'media-contents/uploads/missing.wav', 'processing_status' => 'failed']);
        $bare = $this->episode(['slug' => 'bare', 'processing_status' => 'failed']);

        foreach ([$gone, $bare] as $episode) {
            $this->post("/admin/media-contents/{$episode->id}/retry-audio")->assertRedirect()->assertSessionHas('status');
            $this->assertSame('failed', $episode->fresh()->processing_status);
        }

        Queue::assertNothingPushed();
    }

    public function test_a_failed_episode_shows_its_reason_and_a_retry_button(): void
    {
        // Songs: the Article page's "All" tab lists them (and podcasts, news, health, economy).
        $song = Category::where('slug', 'song')->firstOrFail()->id;
        $failed = $this->episode(['slug' => 'failed', 'category_id' => $song, 'source_url' => self::LINK, 'processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot']);
        $ready = $this->episode(['slug' => 'fine', 'title' => 'Fine One', 'category_id' => $song, 'stream' => 'media-contents/streams/a.opus', 'processing_status' => 'ready']);

        // The edit page: the reason in plain sight, and a button for a separate form (forms cannot be nested).
        $this->get("/admin/media-contents/{$failed->id}/edit")
            ->assertOk()
            ->assertSee('Sign in to confirm you are not a bot')
            ->assertSee('form="retry-audio-form"', false)
            ->assertSee('id="retry-audio-form"', false)
            ->assertSee("/admin/media-contents/{$failed->id}/retry-audio", false);

        // The Article list: a Retry beside the Failed badge, and none for an episode that is fine.
        $list = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString("/admin/media-contents/{$failed->id}/retry-audio", $list);
        $this->assertStringNotContainsString("/admin/media-contents/{$ready->id}/retry-audio", $list);

        $this->get("/admin/media-contents/{$ready->id}/edit")
            ->assertOk()
            ->assertDontSee('id="retry-audio-form"', false);
    }

    public function test_without_permission_to_edit_there_is_no_retry(): void
    {
        $failed = $this->episode([
            'category_id' => Category::where('slug', 'song')->firstOrFail()->id, 'source_url' => self::LINK, 'processing_status' => 'failed',
        ]);

        // createAdmin() reuses one shared role, and setUp already gave it edit rights: take them off for this admin.
        Role::where('slug', 'test-admin')->firstOrFail()->permissions()
            ->detach(Permission::where('slug', 'edit-media-content')->value('id'));
        $this->actingAs($this->createAdmin(['view-media-content']), 'admin');

        $this->post("/admin/media-contents/{$failed->id}/retry-audio")->assertForbidden();
        $this->assertSame('failed', $failed->fresh()->processing_status);

        $list = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('Failed', $list, 'the failed episode is on the page');
        // (The shared script names the data-retry-audio hook on every page; what must be absent is the route itself.)
        $this->assertStringNotContainsString('/retry-audio', $list);
    }
}
