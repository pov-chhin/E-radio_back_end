<?php

namespace Src\CmsAdmin\Http\Controllers\Concerns;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Catalog\Services\HlsAudioProcessor;
use Src\Catalog\Services\ImageVariantProcessor;
use Src\Catalog\Services\UnreadableImageException;

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
     *
     * $disk is where a new image is written (null: the local public disk) and
     * $existingDisk where the image being replaced or removed lives.
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
        ?string $disk = null,
        ?string $existingDisk = null,
    ): ?string {
        // The new image is read before the old one is touched, and one that can't be read comes back as that
        // field's error on the form (with what was typed kept) instead of an error page — the old image stays.
        if ($request->hasFile($field)) {
            try {
                $path = $imageProcessor->process($request->file($field), $directory, $width, $height, crop: $crop, disk: $disk);
            } catch (UnreadableImageException $e) {
                throw ValidationException::withMessages([$field => $e->getMessage()]);
            }

            $this->deleteStoredMedia($existingPath, $existingDisk);

            return $path;
        }

        if ($request->filled("{$field}_url")) {
            try {
                $path = $imageProcessor->processFromUrl($request->input("{$field}_url"), $directory, $width, $height, crop: $crop, disk: $disk);
            } catch (UnreadableImageException $e) {
                throw ValidationException::withMessages(["{$field}_url" => $e->getMessage()]);
            }

            $this->deleteStoredMedia($existingPath, $existingDisk);

            return $path;
        }

        if ($request->boolean("remove_{$field}")) {
            $this->deleteStoredMedia($existingPath, $existingDisk);

            return null;
        }

        return $existingPath;
    }

    /**
     * resolveImageField() for a slot that follows Settings → Storage: a new upload or pasted URL lands in the
     * image folder of the storage the admin chose, and the record remembers that disk in its `{$field}_disk`
     * column (NULL = local) so switching storage later only affects new uploads. A removed image leaves no
     * disk behind; an untouched one keeps its own.
     *
     * @return array<string, ?string> the field's path and its disk column, ready to merge into the validated data
     */
    private function resolveStoredImage(
        ImageVariantProcessor $imageProcessor,
        Request $request,
        string $field,
        ?Model $existing,
        int $width,
        int $height,
        bool $crop = true,
    ): array {
        $diskColumn = "{$field}_disk";
        $replaced = $request->hasFile($field) || $request->filled("{$field}_url");

        $path = $this->resolveImageField(
            $imageProcessor, $request, $field, $existing?->{$field},
            config('services.media_storage.image_directory'), $width, $height,
            crop: $crop, disk: MediaStorage::current(), existingDisk: $existing?->{$diskColumn},
        );

        return [
            $field => $path,
            $diskColumn => match (true) {
                $replaced => MediaStorage::column(),
                $path === null => null,
                default => $existing?->{$diskColumn},
            },
        ];
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

    /**
     * Deletes a stored file from the disk it lives on (null: the local public disk).
     * An HLS playlist has sibling .ts segment files, so its whole directory goes with it.
     */
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
