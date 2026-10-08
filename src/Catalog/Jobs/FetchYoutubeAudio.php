<?php

namespace Src\Catalog\Jobs;

use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\OpusAudioProcessor;
use Src\Catalog\Services\YoutubeAudioFetcher;
use Src\Setting\Models\StorageSetting;
use Throwable;

/**
 * An episode whose audio is a YouTube link: fetches the audio (yt-dlp), shrinks it with FFmpeg to a small
 * low-bitrate Opus file and swaps it in as the episode's stream. The app then plays that file like any other —
 * audio only. Until it is ready the episode stays "processing" (so it is not public), and any earlier stream
 * stays where it is until the new file replaces it.
 */
class FetchYoutubeAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Download (services.yt_dlp.timeout) plus conversion (services.ffmpeg.opus_timeout), with room to spare. */
    public int $timeout = 720;

    public function __construct(
        public readonly int $mediaContentId,
        public readonly string $url,
    ) {}

    public function handle(YoutubeAudioFetcher $fetcher, OpusAudioProcessor $processor): void
    {
        $mediaContent = MediaContent::find($this->mediaContentId);

        // Gone, already done, or the link was changed or replaced by an upload since this job was queued.
        if (! $mediaContent || $mediaContent->processing_status !== 'processing' || $mediaContent->source_url !== $this->url) {
            return;
        }

        $downloaded = null;

        try {
            // A queue worker keeps the settings it booted with, so re-read them: the admin may have switched storage.
            StorageSetting::first()?->applyToConfig();
            $targetDisk = MediaStorage::current();

            $downloaded = $fetcher->download($this->url);

            $convertedPath = $processor->process(
                $downloaded,
                config('services.media_storage.audio_directory'),
                $targetDisk,
                (string) config('services.ffmpeg.youtube_bitrate', '24k'),
                (int) config('services.ffmpeg.youtube_channels', 1) ?: null,
            );
            $mediaContent->refresh();

            // Another link or an upload took over while this one was downloading.
            if ($mediaContent->source_url !== $this->url) {
                MediaStorage::disk($targetDisk)->delete($convertedPath);

                return;
            }

            $duration = $processor->probeDurationSeconds($downloaded);

            [$previousStream, $previousDisk] = [$mediaContent->stream, $mediaContent->stream_disk];
            $updates = [
                'stream' => $convertedPath,
                'stream_disk' => MediaStorage::column($targetDisk),
                'processing_status' => 'ready',
                'processing_error' => null,
            ];

            if ($duration !== null) {
                $updates['duration_seconds'] = $duration;
            }

            $mediaContent->update($updates);

            $this->deleteStoredMedia($previousStream, $previousDisk);
        } catch (Throwable $exception) {
            $this->markFailed($exception);

            throw $exception;
        } finally {
            if ($downloaded !== null) {
                @unlink($downloaded);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            $this->markFailed($exception);
        }
    }

    private function markFailed(Throwable $exception): void
    {
        MediaContent::query()
            ->whereKey($this->mediaContentId)
            ->where('source_url', $this->url)
            ->where('processing_status', 'processing')
            ->update([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
    }

    private function deleteStoredMedia(?string $path, ?string $disk = null): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        if (str_ends_with($path, '.m3u8')) {
            MediaStorage::disk($disk)->deleteDirectory(dirname($path));

            return;
        }

        MediaStorage::disk($disk)->delete($path);
    }
}
