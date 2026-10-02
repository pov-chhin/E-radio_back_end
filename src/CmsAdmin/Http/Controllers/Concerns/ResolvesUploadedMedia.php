<?php

namespace Src\CmsAdmin\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Services\HlsAudioProcessor;
use Src\Catalog\Services\ImageVariantProcessor;

/**
 * Shared by every controller with a field that accepts either a file upload
 * or a pasted URL (artist photo/cover, radio channel cover, media content
 * stream/thumbnail, …).
 */
trait ResolvesUploadedMedia
{
    /**
     * For image slots with a fixed size (photo, cover, thumbnail): new
     * upload or pasted URL → downloaded, cropped/resized, re-encoded as
     * WebP, replacing the old file; "remove" checked → clear it; otherwise
     * keep whatever's already there.
     */
    private function resolveImageField(
        ImageVariantProcessor $imageProcessor,
        Request $request,
        string $field,
        ?string $existingPath,
        string $directory,
        int $width,
        int $height,
        bool $crop = true,
    ): ?string {
        if ($request->hasFile($field)) {
            $this->deleteStoredMedia($existingPath);

            return $imageProcessor->process($request->file($field), $directory, $width, $height, crop: $crop);
        }

        if ($request->filled("{$field}_url")) {
            $this->deleteStoredMedia($existingPath);

            return $imageProcessor->processFromUrl($request->input("{$field}_url"), $directory, $width, $height, crop: $crop);
        }

        if ($request->boolean("remove_{$field}")) {
            $this->deleteStoredMedia($existingPath);

            return null;
        }

        return $existingPath;
    }

    /**
     * For arbitrary files (audio/video streams, …) where there's nothing to
     * crop or re-encode: an upload is stored as-is; a pasted URL is kept
     * exactly as given — never downloaded, since it could be a large media
     * file or an endless live stream rather than a small thumbnail.
     */
    private function resolveFileField(Request $request, string $field, ?string $existingPath, string $directory): ?string
    {
        if ($request->hasFile($field)) {
            $this->deleteStoredMedia($existingPath);

            return $request->file($field)->store($directory, 'public');
        }

        if ($request->filled("{$field}_url")) {
            $this->deleteStoredMedia($existingPath);

            return $request->input("{$field}_url");
        }

        if ($request->boolean("remove_{$field}")) {
            $this->deleteStoredMedia($existingPath);

            return null;
        }

        return $existingPath;
    }

    /**
     * Uploaded audio and video files are reduced to their first audio stream
     * and stored as single-bitrate HLS (AAC) — plays natively on every
     * platform, unlike Opus. External URLs stay untouched because they may
     * point to live or unbounded streams that cannot be safely transcoded here.
     */
    private function resolveAudioField(
        HlsAudioProcessor $audioProcessor,
        Request $request,
        string $field,
        ?string $existingPath,
        string $directory,
    ): ?string {
        if ($request->hasFile($field)) {
            $convertedPath = $audioProcessor->process($request->file($field), $directory);
            $this->deleteStoredMedia($existingPath);

            return $convertedPath;
        }

        if ($request->filled("{$field}_url")) {
            $this->deleteStoredMedia($existingPath);

            return $request->input("{$field}_url");
        }

        if ($request->boolean("remove_{$field}")) {
            $this->deleteStoredMedia($existingPath);

            return null;
        }

        return $existingPath;
    }

    /** An HLS playlist has sibling .ts segment files, so its whole directory goes with it. */
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
