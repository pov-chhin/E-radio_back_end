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
            '--no-warnings',
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

        [$cookies, $temporaryCookies] = $this->cookiesFile();

        if ($cookies !== null) {
            $command[] = '--cookies';
            $command[] = $cookies;
        }

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

                if ($attempt >= $attempts || ! self::isTransient($process->getErrorOutput())) {
                    throw new RuntimeException($this->failureMessage($process));
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
            $path = tempnam(sys_get_temp_dir(), 'yt_cookies_');

            if ($path === false || file_put_contents($path, $contents) === false) {
                throw new RuntimeException('The saved YouTube cookies could not be prepared for yt-dlp.');
            }

            @chmod($path, 0600);

            return [$path, $path];
        }

        $configured = config('services.yt_dlp.cookies');

        return [filled($configured) ? (string) $configured : null, null];
    }

    /** Whether trying again could help: a refused or dropped request, not a video that is private, gone or blocked. */
    public static function isTransient(string $errorOutput): bool
    {
        return (bool) preg_match('/HTTP Error (403|429|5\d\d)|timed out|connection (reset|aborted|refused)|temporary failure|remote end closed|incomplete read/i', $errorOutput);
    }

    private function discard(string $base): void
    {
        foreach (glob($base.'.*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function failureMessage(Process $process): string
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

        if ($error === null && $process->isSuccessful()) {
            return 'YouTube gave no audio for this link (live streams cannot be fetched).';
        }

        return 'YouTube audio could not be fetched: '.($error ?? ($lines[array_key_last($lines)] ?? 'no reason given'));
    }

    private static function isHost(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
