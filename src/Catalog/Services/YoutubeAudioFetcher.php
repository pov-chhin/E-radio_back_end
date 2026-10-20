<?php

namespace Src\Catalog\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;
use Src\Setting\Models\StorageSetting;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Pulls the audio track of a YouTube video down to a temporary local file with yt-dlp, ready for FFmpeg
 * ({@see OpusAudioProcessor}) to shrink. The app plays the result as an ordinary audio file, so a YouTube
 * episode is "audio only" and never touches YouTube from the phone.
 */
class YoutubeAudioFetcher
{
    private const PATH_PREFIXES = ['embed', 'shorts', 'live', 'v'];

    /** The 11-character video id of a YouTube link (watch, youtu.be, shorts, embed, music.youtube.com …), or null. */
    public static function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn (string $segment) => $segment !== ''));
        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = $segments[0] ?? null;
        } elseif (self::isHost($host, 'youtube.com') || self::isHost($host, 'youtube-nocookie.com')) {
            if (count($segments) >= 2 && in_array($segments[0], self::PATH_PREFIXES, true)) {
                $candidate = $segments[1];
            } elseif (($segments[0] ?? null) === 'watch') {
                parse_str($parts['query'] ?? '', $query);
                $candidate = is_string($query['v'] ?? null) ? $query['v'] : null;
            }
        }

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) ? $candidate : null;
    }

    /**
     * Downloads the best audio stream of the video and returns the path of the temporary file (the caller
     * deletes it). Throws a RuntimeException whose message is fit to show an admin when YouTube refuses.
     */
    public function download(string $url): string
    {
        $videoId = self::videoId($url) ?? throw new RuntimeException('That is not a YouTube video link.');
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'yt_audio_'.Str::uuid();

        $command = [
            config('services.yt_dlp.binary', 'yt-dlp'),
            '--no-playlist',
            '--no-progress',
            // A live stream never ends, so there is nothing to fetch.
            '--match-filter', '!is_live',
            '--max-filesize', (string) config('services.yt_dlp.max_filesize', '300M'),
            '-f', 'bestaudio/best',
            '-o', $base.'.%(ext)s',
        ];

        // YouTube hands out a 403 instead of the audio unless yt-dlp can run its JavaScript challenge in Deno or Node.
        foreach ((array) config('services.yt_dlp.js_runtimes', []) as $runtime) {
            $command[] = '--js-runtimes';
            $command[] = $runtime;
        }

        array_push($command, ...$this->potProviderArguments());

        $proxyArguments = $this->proxyArguments();
        $proxyOffset = count($command);
        array_push($command, ...$proxyArguments);
        $proxyUnreachable = false;

        [$cookies, $temporaryCookies] = $this->cookiesFile();

        // An Admin upload is an explicit choice and wins over the optional local-browser fallback.
        array_push($command, ...$this->authenticationArguments($cookies, $temporaryCookies !== null));

        // Always the canonical address built from the id, never the pasted text (tracking parameters, other hosts).
        $command[] = 'https://www.youtube.com/watch?v='.$videoId;

        $attempts = max(1, (int) config('services.yt_dlp.attempts', 3));

        try {
            for ($attempt = 1; ; $attempt++) {
                $process = new Process($command);
                $process->setTimeout(max(1, (int) config('services.yt_dlp.timeout', 300)));

                try {
                    $process->run();
                } catch (ProcessTimedOutException) {
                    $this->discard($base);

                    throw new RuntimeException('Fetching the audio from YouTube took too long.');
                } catch (\Symfony\Component\Process\Exception\RuntimeException $exception) {
                    $this->discard($base);

                    throw new RuntimeException('yt-dlp could not be started: '.$exception->getMessage());
                }

                $files = array_values(array_filter(
                    glob($base.'.*') ?: [],
                    fn (string $file) => ! preg_match('/\.(part|ytdl|temp)$/', $file),
                ));

                if ($process->isSuccessful() && $files !== []) {
                    break;
                }

                $this->discard($base);

                // A dead proxy (wrong host, nothing listening, bad login) must not stop every download: YouTube may
                // still answer this server directly, so go around the proxy once, at once, and say so if that fails too.
                if ($proxyArguments !== [] && self::isProxyFailure($process->getErrorOutput())) {
                    array_splice($command, $proxyOffset, count($proxyArguments));
                    $proxyArguments = [];
                    $proxyUnreachable = true;
                    $attempt--;

                    continue;
                }

                if ($attempt >= $attempts || ! self::isTransient($process->getErrorOutput())) {
                    throw new RuntimeException($this->failureMessage(
                        $process,
                        signedIn: $cookies !== null || filled(config('services.yt_dlp.cookies_from_browser')),
                        proxied: $proxyArguments !== [],
                    ).($proxyUnreachable ? ' The proxy in YT_DLP_PROXY could not be reached, so YouTube was tried directly: fix or clear YT_DLP_PROXY.' : ''));
                }

                // YouTube sometimes refuses a request for no lasting reason (several in a row make it likelier).
                sleep(3 * $attempt);
            }

            // Anything else it left beside the audio (thumbnails, partial files) is not wanted.
            foreach (array_slice($files, 1) as $extra) {
                @unlink($extra);
            }

            return $files[0];
        } finally {
            if ($temporaryCookies !== null) {
                @unlink($temporaryCookies);
            }
        }
    }

    /**
     * Prefer cookies uploaded in Admin Settings. They are decrypted only long enough to create a private temp file;
     * the configured filesystem path remains a fallback for installations that already manage their own secret.
     *
     * @return array{0: ?string, 1: ?string} [path passed to yt-dlp, temporary path to remove]
     */
    private function cookiesFile(): array
    {
        try {
            $contents = StorageSetting::first()?->youtube_cookies;
        } catch (QueryException|DecryptException) {
            $contents = null;
        }

        if (filled($contents)) {
            if (! self::validCookies($contents)) {
                throw new RuntimeException('The saved YouTube cookies are invalid or empty. Upload a fresh Netscape cookies.txt in Admin Settings → YouTube.');
            }

            $path = tempnam(sys_get_temp_dir(), 'yt_cookies_');

            if ($path === false || file_put_contents($path, $contents) === false) {
                throw new RuntimeException('The saved YouTube cookies could not be prepared for yt-dlp.');
            }

            @chmod($path, 0600);

            return [$path, $path];
        }

        $configured = config('services.yt_dlp.cookies');

        if (! filled($configured) || ! is_file((string) $configured)) {
            return [null, null];
        }

        $contents = @file_get_contents((string) $configured);

        if (! is_string($contents) || ! self::validCookies($contents)) {
            throw new RuntimeException('The configured YouTube cookies file is invalid or empty. Export fresh YouTube cookies in Netscape format, then upload them in Admin Settings → YouTube.');
        }

        return [(string) $configured, null];
    }

    /** @return list<string> */
    private function authenticationArguments(?string $cookies, bool $uploadedCookies = false): array
    {
        if ($uploadedCookies && $cookies !== null) {
            return ['--cookies', $cookies];
        }

        $browser = config('services.yt_dlp.cookies_from_browser');

        if (filled($browser)) {
            return ['--cookies-from-browser', (string) $browser];
        }

        if ($cookies !== null) {
            return ['--cookies', $cookies];
        }

        $visitorData = config('services.yt_dlp.visitor_data');

        if (! filled($visitorData)) {
            return [];
        }

        return [
            '--extractor-args', 'youtubetab:skip=webpage',
            '--extractor-args', 'youtube:player_skip=webpage,configs;visitor_data='.(string) $visitorData,
        ];
    }

    /** yt-dlp requires a Netscape cookie jar containing at least one YouTube cookie. */
    private static function validCookies(string $contents): bool
    {
        if (! str_contains($contents, 'Netscape HTTP Cookie File') && ! str_contains($contents, 'HTTP Cookie File')) {
            return false;
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || (str_starts_with($line, '#') && ! str_starts_with($line, '#HttpOnly_'))) {
                continue;
            }

            $columns = explode("\t", $line);
            $domain = strtolower(str_replace('#HttpOnly_', '', $columns[0] ?? ''));

            if (count($columns) >= 7 && ($domain === 'youtube.com' || str_ends_with($domain, '.youtube.com'))) {
                return true;
            }
        }

        return false;
    }

    /** Whether trying again could help: a refused or dropped request, not a video that is private, gone or blocked. */
    public static function isTransient(string $errorOutput): bool
    {
        return (bool) preg_match('/HTTP Error (403|429|5\d\d)|timed out|connection (reset|aborted|refused)|temporary failure|remote end closed|incomplete read/i', $errorOutput);
    }

    /** Whether yt-dlp could not get through its proxy at all (refused, unresolvable, rejected login), as opposed to YouTube refusing. */
    public static function isProxyFailure(string $errorOutput): bool
    {
        return (bool) preg_match('/unable to connect to proxy|proxyerror|tunnel connection failed|socks\w* (proxy )?(error|failed)/i', $errorOutput);
    }

    private function discard(string $base): void
    {
        foreach (glob($base.'.*') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * YouTube answers "sign in to confirm you're not a bot" to a datacenter address (a VPS) but not to a home
     * one, so a server can send just this request through a proxy that leaves from such an address
     * (YT_DLP_PROXY: http://, https://, socks5:// or socks5h://, with user:pass@ if it needs them).
     *
     * @return list<string>
     */
    private function proxyArguments(): array
    {
        $proxy = trim((string) config('services.yt_dlp.proxy'));

        if ($proxy === '') {
            return [];
        }

        if (! preg_match('#^(https?|socks4a?|socks5h?)://[^\s/]+#i', $proxy)) {
            throw new RuntimeException('YT_DLP_PROXY is not a proxy address. Use a form like socks5://host:1080 or http://user:password@host:8080.');
        }

        return ['--proxy', $proxy];
    }

    /**
     * YouTube answers some downloads with 403 unless the request carries a "PO token". The bgutil plugin inside yt-dlp
     * fetches one from a token server (YT_DLP_POT_BASE_URL); without that setting the plugin only tries its default,
     * a server on this same machine, and yt-dlp carries on without a token.
     *
     * @return list<string>
     */
    private function potProviderArguments(): array
    {
        $baseUrl = trim((string) config('services.yt_dlp.pot_base_url'));

        if ($baseUrl === '') {
            return [];
        }

        // A semicolon would start another extractor argument.
        if (! preg_match('#^https?://[^\s;/]+(/[^\s;]*)?$#i', $baseUrl)) {
            throw new RuntimeException('YT_DLP_POT_BASE_URL is not a web address. Use a form like http://pot-provider:4416.');
        }

        return ['--extractor-args', 'youtubepot-bgutilhttp:base_url='.rtrim($baseUrl, '/')];
    }

    /** The proxy address (and its password) can show up in yt-dlp's errors, which are stored and shown to admins. */
    private function withoutProxySecrets(string $text): string
    {
        $proxy = trim((string) config('services.yt_dlp.proxy'));

        if ($proxy === '') {
            return $text;
        }

        $secrets = array_filter([$proxy, parse_url($proxy, PHP_URL_PASS)], fn ($secret) => is_string($secret) && $secret !== '');

        return str_replace($secrets, '[proxy]', $text);
    }

    private function failureMessage(Process $process, bool $signedIn = false, bool $proxied = false): string
    {
        // 127 is the shell's "command not found"; Windows words it differently.
        if ($process->getExitCode() === 127
            || preg_match('/is not recognized as an internal or external command|command not found|: not found/i', $process->getErrorOutput())) {
            return 'yt-dlp is not installed on the server (or YT_DLP_BINARY points to the wrong place).';
        }

        $lines = array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/', $process->getErrorOutput()."\n".$process->getOutput()) ?: [],
        )));
        $error = null;

        foreach (array_reverse($lines) as $line) {
            if (str_starts_with($line, 'ERROR:')) {
                $error = trim(substr($line, 6));
                break;
            }
        }

        if ($error !== null && preg_match('/sign in to confirm .+ not a bot/i', $error)) {
            // The same reply whether or not cookies were sent, so say which it was: "upload cookies" is wrong advice once
            // they are saved — YouTube then refuses the server's address, which only a home-style address gets past.
            if ($proxied) {
                return 'YouTube still asks to sign in even through the proxy (YT_DLP_PROXY): the proxy\'s address is blocked too. Use a residential (home-style) proxy, or upload the audio file yourself.';
            }

            if ($signedIn) {
                return 'YouTube still asks this server to sign in even with the saved cookies. It blocks most server (datacenter) addresses, so cookies often do not help: send the downloads through a proxy that leaves from a home address (YT_DLP_PROXY), or upload the audio file yourself. If the cookies are old, upload fresh ones first.';
            }

            return 'YouTube asks this server to sign in. Upload a fresh Netscape cookies.txt in Admin Settings → YouTube, or send the downloads through a home-address proxy (YT_DLP_PROXY), then retry. A YouTube API key cannot replace browser cookies.';
        }

        // "An extractor error has occurred (caused by KeyError(...))": yt-dlp itself broke on YouTube's page, which in
        // practice means this server's yt-dlp is too old for YouTube's current site (or the saved cookies are stale).
        if ($error !== null && preg_match('/an extractor error has occurred|\(caused by \w*Error\(/i', $error)) {
            $warning = collect($lines)
                ->reverse()
                ->first(fn (string $line) => str_starts_with($line, 'WARNING:'));
            $details = $warning !== null
                ? ' The preceding yt-dlp warning was: '.Str::limit(trim(substr($warning, 8)), 500)
                : '';

            return 'yt-dlp on this server could not read YouTube\'s page ('.$this->withoutProxySecrets(Str::before($error, '; please report')).').'.$this->withoutProxySecrets($details).' It may be out of date: rebuild and redeploy the server so it installs the newest yt-dlp, then retry. If it still fails, use the warning above to diagnose the server connection, upload fresh cookies in Admin Settings → YouTube, or upload the audio file yourself.';
        }

        if ($error === null && $process->isSuccessful()) {
            return 'YouTube gave no audio for this link (live streams cannot be fetched).';
        }

        // A bare "HTTP Error 403: Forbidden" says nothing on its own; the warnings just before it usually name the cause
        // (a format that needs a PO token, a client that was skipped, …).
        $warnings = collect($lines)
            ->filter(fn (string $line) => str_starts_with($line, 'WARNING:'))
            ->map(fn (string $line) => trim(substr($line, 8)))
            ->unique()
            ->take(-3)
            ->implode(' | ');
        $details = $error !== null && $warnings !== ''
            ? ' yt-dlp also warned: '.Str::limit($warnings, 600)
            : '';

        return 'YouTube audio could not be fetched: '.$this->withoutProxySecrets($error ?? ($lines[array_key_last($lines)] ?? 'no reason given')).$this->withoutProxySecrets($details);
    }

    private static function isHost(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
