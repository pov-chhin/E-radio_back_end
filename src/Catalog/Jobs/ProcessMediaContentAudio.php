<?php

namespace Src\Catalog\Jobs;

use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\M4aAudioProcessor;
use Src\Setting\Models\StorageSetting;
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

    public function handle(M4aAudioProcessor $processor): void
    {
        $mediaContent = MediaContent::find($this->mediaContentId);

        if (! $mediaContent || $mediaContent->processing_status !== 'processing' || $mediaContent->source_path !== $this->sourcePath) {
            return;
        }

        try {
            // A queue worker keeps the settings it booted with, so re-read them: the admin may have switched
            // storage (or its folders) since. The upload itself always waits on the local disk for FFmpeg;
            // only the converted result goes to the chosen storage.
            StorageSetting::first()?->applyToConfig();

            $disk = Storage::disk('public');
            $targetDisk = MediaStorage::current();

            if (! $disk->exists($this->sourcePath)) {
                throw new \RuntimeException('The uploaded source file is missing.');
            }

            $convertedPath = $processor->process($disk->path($this->sourcePath), config('services.media_storage.audio_directory'), $targetDisk);
            $mediaContent->refresh();

            // A newer upload superseded this job while FFmpeg was running.
            if ($mediaContent->source_path !== $this->sourcePath) {
                MediaStorage::disk($targetDisk)->delete($convertedPath);

                return;
            }

            $duration = $processor->probeDurationSeconds($disk->path($this->sourcePath));

            [$previousStream, $previousDisk] = [$mediaContent->stream, $mediaContent->stream_disk];
            $updates = [
                'stream' => $convertedPath,
                'stream_disk' => MediaStorage::column($targetDisk),
                'source_path' => null,
                'processing_status' => 'ready',
                'processing_error' => null,
            ];

            if ($duration !== null) {
                $updates['duration_seconds'] = $duration;
            }

            $mediaContent->update($updates);

            $disk->delete($this->sourcePath);
            $this->deleteStoredMedia($previousStream, $previousDisk);
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
