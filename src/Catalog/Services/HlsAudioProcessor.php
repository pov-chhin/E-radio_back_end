<?php

namespace Src\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Single-bitrate HLS (AAC), not Opus: unlike Opus, HLS+AAC plays natively on
 * every platform (iOS's AVPlayer has no built-in Opus/OGG support).
 */
class HlsAudioProcessor
{
    public function process(UploadedFile $upload, string $directory): string
    {
        $id = (string) Str::uuid();
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'media_hls_'.$id;
        $processTimeout = max(1, (int) config('services.ffmpeg.hls_timeout', 300));

        // Large uploads can legitimately take longer than PHP's usual 60-second
        // web-request limit. Keep PHP alive slightly longer than Symfony's own
        // process timeout so Process can terminate FFmpeg and report a useful
        // validation error instead of PHP dying inside WindowsPipes.
        $phpTimeLimit = (int) ini_get('max_execution_time');

        if ($phpTimeLimit > 0 && $phpTimeLimit <= $processTimeout) {
            @set_time_limit($processTimeout + 30);
        }

        if (! mkdir($tempDir) && ! is_dir($tempDir)) {
            throw new RuntimeException('Unable to create a temporary directory for HLS output.');
        }

        $playlistPath = $tempDir.DIRECTORY_SEPARATOR.'playlist.m3u8';

        try {
            $process = new Process([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-i', $upload->getRealPath(),
                '-map', '0:a:0',
                '-vn',
                '-c:a', 'aac',
                '-b:a', config('services.ffmpeg.hls_bitrate', '128k'),
                '-f', 'hls',
                '-hls_time', '6',
                '-hls_playlist_type', 'vod',
                '-hls_segment_filename', $tempDir.DIRECTORY_SEPARATOR.'segment%03d.ts',
                $playlistPath,
            ]);
            $process->setTimeout($processTimeout);
            $process->mustRun();

            $storedDir = trim($directory, '/').'/'.$id;

            foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                $stream = fopen($file, 'rb');

                if ($stream === false || ! Storage::disk('public')->put($storedDir.'/'.basename($file), $stream)) {
                    throw new RuntimeException('Unable to store an HLS output file.');
                }

                fclose($stream);
            }

            return $storedDir.'/playlist.m3u8';
        } catch (\Throwable $exception) {
            throw new RuntimeException('The media file could not be converted to HLS.', 0, $exception);
        } finally {
            foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }
}
