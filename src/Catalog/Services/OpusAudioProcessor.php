<?php

namespace Src\Catalog\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class OpusAudioProcessor
{
    public function process(string $sourcePath, string $directory): string
    {
        $id = (string) Str::uuid();
        $temporaryOutput = sys_get_temp_dir().DIRECTORY_SEPARATOR."media_opus_{$id}.opus";
        $storedPath = trim($directory, '/').'/'.$id.'.opus';
        $timeout = max(1, (int) config('services.ffmpeg.opus_timeout', 300));

        try {
            $process = new Process([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $sourcePath,
                '-map', '0:a:0',
                '-vn',
                '-c:a', 'libopus',
                '-b:a', config('services.ffmpeg.opus_bitrate', '64k'),
                '-vbr', 'on',
                '-application', 'audio',
                $temporaryOutput,
            ]);
            $process->setTimeout($timeout);
            $process->mustRun();

            $stream = fopen($temporaryOutput, 'rb');

            if ($stream === false || ! Storage::disk('public')->put($storedPath, $stream)) {
                throw new RuntimeException('Unable to store the converted Opus file.');
            }

            fclose($stream);

            return $storedPath;
        } catch (\Throwable $exception) {
            throw new RuntimeException('The media file could not be converted to Opus.', 0, $exception);
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
