<?php

namespace Src\Catalog\Services;

use App\Support\MediaStorage;
use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Center-crops a source image to a target aspect ratio, resizes it to exact
 * target dimensions, and encodes it as WebP — used for the fixed image slots
 * (artist photo, cover, …) that the admin UI expects at a precise size
 * regardless of whether the admin uploaded a file or pasted a URL.
 */
class ImageVariantProcessor
{
    /**
     * With $crop = false the image keeps its own aspect ratio: $width × $height
     * is only the largest box it is scaled down to fit (never scaled up), so
     * nothing is cut off.
     */
    public function process(UploadedFile $file, string $directory, int $width, int $height, int $quality = 82, bool $crop = true, ?string $disk = null): string
    {
        return $this->processBytes(
            file_get_contents($file->getRealPath()), $directory, $width, $height, $quality, $crop, $disk,
            __('That file could not be read as a picture. It may be damaged, an animated WebP or an unsupported type — try a JPEG or PNG.'),
        );
    }

    /**
     * Downloads the image server-side and runs it through the same crop/resize/WebP pipeline as an upload, so a pasted URL still ends up matching spec exactly.
     *
     * @throws UnreadableImageException when the address can't be downloaded or isn't a picture we can read
     */
    public function processFromUrl(string $url, string $directory, int $width, int $height, int $quality = 82, bool $crop = true, ?string $disk = null): string
    {
        try {
            $response = Http::timeout(10)->get($url);
        } catch (ConnectionException) {
            throw new UnreadableImageException(__('Could not download that image URL.'));
        }

        if (! $response->successful()) {
            throw new UnreadableImageException(__('Could not download that image URL.'));
        }

        return $this->processBytes(
            $response->body(), $directory, $width, $height, $quality, $crop, $disk,
            __('That address did not return a picture we can read. Paste the picture\'s own address (ending in .jpg, .png, .webp), not a web page.'),
        );
    }

    /**
     * $disk is where the finished image is written; null means the local public disk.
     * $unreadable is the message for bytes that aren't a picture GD can decode.
     */
    private function processBytes(string $bytes, string $directory, int $width, int $height, int $quality, bool $crop, ?string $disk, string $unreadable): string
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new UnreadableImageException($unreadable);
        }

        $cropped = $crop
            ? $this->resizeAndCrop($source, $width, $height)
            : $this->resizeToFit($source, $width, $height);

        ob_start();
        imagewebp($cropped, null, $quality);
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($cropped);

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        abort_unless(MediaStorage::disk($disk)->put($path, $contents), 500, 'Could not save the image to storage.');

        return $path;
    }

    private function resizeToFit(GdImage $source, int $maxWidth, int $maxHeight): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, imagecolorallocatealpha($target, 0, 0, 0, 127));

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $target;
    }

    private function resizeAndCrop(GdImage $source, int $targetWidth, int $targetHeight): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, imagecolorallocatealpha($target, 0, 0, 0, 127));

        imagecopyresampled(
            $target, $source,
            0, 0, $cropX, $cropY,
            $targetWidth, $targetHeight, $cropWidth, $cropHeight,
        );

        return $target;
    }
}
