<?php

namespace Src\Catalog\Services;

use App\Support\MediaStorage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * M4A (AAC-LC), not Opus: unlike an Ogg Opus file, M4A plays natively on every platform (iOS's AVPlayer has no
 * built-in Opus/OGG support).
 */
class M4aAudioProcessor
{
    /**
     * Converts $sourcePath (a local file) to M4A — AAC at the configured bitrate (96 kbps) and sample rate
     * (44.1 kHz), channels as in the source — and stores it under $directory on $disk (null: the local public disk).
     */
    public function process(string $sourcePath, string $directory, ?string $disk = null): string
    {
        $id = (string) Str::uuid();
        $temporaryOutput = sys_get_temp_dir().DIRECTORY_SEPARATOR."media_m4a_{$id}.m4a";
        $storedPath = trim($directory, '/').'/'.$id.'.m4a';
        $timeout = max(1, (int) config('services.ffmpeg.audio_timeout', 300));

        try {
            $process = new Process([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $sourcePath,
                '-map', '0:a:0',
                '-vn',
                '-c:a', 'aac',
                '-b:a', config('services.ffmpeg.audio_bitrate', '96k'),
                '-ar', (string) config('services.ffmpeg.audio_sample_rate', 44100),
                // The index goes first in the file, so the app can start playing and seek before it has all of it.
                '-movflags', '+faststart',
                $temporaryOutput,
            ]);
            $process->setTimeout($timeout);
            $process->mustRun();

            $stream = fopen($temporaryOutput, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to read the converted M4A file.');
            }

            try {
                if (! MediaStorage::disk($disk)->put($storedPath, $stream)) {
                    throw new RuntimeException('Unable to store the converted M4A file.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return $storedPath;
        } catch (\Throwable $exception) {
            throw new RuntimeException('The media file could not be converted to M4A.', 0, $exception);
        } finally {
            @unlink($temporaryOutput);
        }
    }

    /**
     * Reads the audio duration off the source file via ffprobe. Returns null
     * (rather than throwing) on any failure — duration is a nice-to-have,
     * not something that should fail the whole conversion job over.
     */
    public function probeDurationSeconds(string $sourcePath): ?int
    {
        try {
            $process = new Process([
                config('services.ffmpeg.probe_binary', 'ffprobe'),
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $sourcePath,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $duration = (float) trim($process->getOutput());

            return $duration > 0 ? (int) round($duration) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
