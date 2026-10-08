<?php

namespace Src\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Src\Catalog\Jobs\FetchYoutubeAudio;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\YoutubeAudioFetcher;

class FetchYoutubeAudioCommand extends Command
{
    protected $signature = 'media:fetch-youtube-audio {--dry-run : List the episodes without queueing them}';

    protected $description = 'Queue the audio fetch for episodes still pointing at a YouTube link (saved before audio was fetched on the server) and for ones whose last fetch failed';

    public function handle(): int
    {
        $queued = 0;

        MediaContent::query()
            ->where(function ($query): void {
                $query
                    ->where(fn ($legacy) => $legacy->whereNull('source_url')->where('stream', 'like', '%youtu%'))
                    ->orWhere(fn ($failed) => $failed->whereNotNull('source_url')->where('processing_status', 'failed'));
            })
            // By id, not by offset: queueing changes the very columns this query filters on.
            ->eachById(function (MediaContent $mediaContent) use (&$queued): void {
                $url = $mediaContent->source_url ?: (string) $mediaContent->stream;

                if (YoutubeAudioFetcher::videoId($url) === null) {
                    return;
                }

                $this->line("  [{$mediaContent->id}] {$mediaContent->title} <- {$url}");
                $queued++;

                if ($this->option('dry-run')) {
                    return;
                }

                // Hidden from the app until the new file is ready (it needs `processing_status = ready` to be public).
                $mediaContent->update([
                    'source_url' => $url,
                    'processing_status' => 'processing',
                    'processing_error' => null,
                ]);

                FetchYoutubeAudio::dispatch($mediaContent->id, $url);
            });

        $this->info($this->option('dry-run')
            ? "{$queued} episode(s) would be queued."
            : "{$queued} episode(s) queued — run the queue worker (php artisan queue:work) to fetch them.");

        return self::SUCCESS;
    }
}
