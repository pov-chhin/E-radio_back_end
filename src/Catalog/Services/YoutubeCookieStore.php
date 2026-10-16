<?php

namespace Src\Catalog\Services;

use Src\Catalog\Jobs\FetchYoutubeAudio;
use Src\Catalog\Models\MediaContent;
use Src\Setting\Models\StorageSetting;

/**
 * Where the YouTube cookies for yt-dlp are kept (encrypted in the database) — shared by the Settings upload and by
 * the browser extension that sends them on its own, so both check and save them the same way.
 */
class YoutubeCookieStore
{
    /** A Netscape/Mozilla cookie file has a supported header and at least one YouTube cookie row. */
    public static function valid(string $contents): bool
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

    /** How many cookies a Netscape file holds (its header and comment lines don't count). */
    public static function count(string $contents): int
    {
        return count(array_filter(
            preg_split('/\R/', $contents) ?: [],
            fn (string $line) => trim($line) !== '' && (! str_starts_with(trim($line), '#') || str_starts_with(trim($line), '#HttpOnly_')),
        ));
    }

    /**
     * The cookies a Netscape file holds, for showing which ones are saved. Values are deliberately left out: they
     * are the signed-in session itself, and the page never displays them. `expires` is a Unix time, 0 for a
     * session cookie. With $youtubeOnly the Google sign-in cookies that sit beside them are left out of the list
     * (they stay in the saved file — yt-dlp needs them).
     *
     * @return list<array{name: string, domain: string, subdomains: bool, path: string, secure: bool, http_only: bool, expires: int}>
     */
    public static function list(string $contents, bool $youtubeOnly = false): array
    {
        $cookies = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || (str_starts_with($line, '#') && ! str_starts_with($line, '#HttpOnly_'))) {
                continue;
            }

            $columns = explode("\t", $line);

            if (count($columns) < 7) {
                continue;
            }

            $host = strtolower(ltrim(str_replace('#HttpOnly_', '', $columns[0]), '.'));

            if ($youtubeOnly && $host !== 'youtube.com' && ! str_ends_with($host, '.youtube.com')) {
                continue;
            }

            $cookies[] = [
                'name' => $columns[5],
                'domain' => str_replace('#HttpOnly_', '', $columns[0]),
                'subdomains' => strtoupper($columns[1]) === 'TRUE',
                'path' => $columns[2],
                'secure' => strtoupper($columns[3]) === 'TRUE',
                'http_only' => str_starts_with($columns[0], '#HttpOnly_'),
                'expires' => max(0, (int) $columns[4]),
            ];
        }

        return $cookies;
    }

    /**
     * Saves the cookies and queues every failed YouTube fetch again with them. The caller has checked them with
     * valid(). Returns how many fetches were queued.
     */
    public function save(string $netscape): int
    {
        StorageSetting::firstOrFail()->update(['youtube_cookies' => $netscape]);

        $retried = 0;

        MediaContent::query()
            ->where('processing_status', 'failed')
            ->whereNotNull('source_url')
            ->eachById(function (MediaContent $mediaContent) use (&$retried): void {
                if (YoutubeAudioFetcher::videoId((string) $mediaContent->source_url) === null) {
                    return;
                }

                $mediaContent->update([
                    'processing_status' => 'processing',
                    'processing_error' => null,
                ]);
                FetchYoutubeAudio::dispatch($mediaContent->id, $mediaContent->source_url)->afterCommit();
                $retried++;
            });

        return $retried;
    }
}
