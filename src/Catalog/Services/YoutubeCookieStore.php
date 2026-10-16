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
