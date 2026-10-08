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
        $this->actingAs($this->createAdmin(['view-media-content']), 'admin');

        $this->post("/admin/media-contents/{$failed->id}/retry-audio")->assertForbidden();
        $this->assertSame('failed', $failed->fresh()->processing_status);

        $list = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('Failed', $list, 'the failed episode is on the page');
        $this->assertStringNotContainsString('retry-audio', $list);
    }
}
