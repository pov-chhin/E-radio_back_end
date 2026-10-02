<?php

namespace Src\Catalog\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\OpusAudioProcessor;
use Throwable;

class ProcessMediaContentAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public function __construct(
        public readonly int $mediaContentId,
        public readonly string $sourcePath,
    ) {}

    public function handle(OpusAudioProcessor $processor): void
    {
        $mediaContent = MediaContent::find($this->mediaContentId);

        if (! $mediaContent || $mediaContent->processing_status !== 'processing' || $mediaContent->source_path !== $this->sourcePath) {
            return;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($this->sourcePath)) {
                throw new \RuntimeException('The uploaded source file is missing.');
            }

            $convertedPath = $processor->process($disk->path($this->sourcePath), config('services.media_storage.audio_directory'));
            $mediaContent->refresh();

            // A newer upload superseded this job while FFmpeg was running.
            if ($mediaContent->source_path !== $this->sourcePath) {
                $disk->delete($convertedPath);

                return;
            }

            $duration = $processor->probeDurationSeconds($disk->path($this->sourcePath));

            $previousStream = $mediaContent->stream;
            $updates = [
                'stream' => $convertedPath,
                'source_path' => null,
                'processing_status' => 'ready',
                'processing_error' => null,
            ];

            if ($duration !== null) {
                $updates['duration_seconds'] = $duration;
            }

            $mediaContent->update($updates);

            $disk->delete($this->sourcePath);
            $this->deleteStoredMedia($previousStream);
        } catch (Throwable $exception) {
            $this->markFailed($exception);

            throw $exception;
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
            ->where('source_path', $this->sourcePath)
            ->update([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
    }

    private function deleteStoredMedia(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        if (str_ends_with($path, '.m3u8')) {
            Storage::disk('public')->deleteDirectory(dirname($path));

            return;
        }

        Storage::disk('public')->delete($path);
    }
}
