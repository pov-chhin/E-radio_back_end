<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where media content (audio streams and thumbnails) is stored: the local public disk, or the S3-compatible
 * disk the admin configures under Settings → Storage. Each stored file remembers its own disk (a NULL
 * `*_disk` column means local), so switching storage only affects new uploads.
 */
final class MediaStorage
{
    public const LOCAL = 'public';

    public const S3 = 'media_s3';

    public const RUSTFS = 'media_rustfs';

    /** The disk new media content uploads go to right now. */
    public static function current(): string
    {
        return config('services.media_storage.disk', self::LOCAL);
    }

    /** What to store in a record's `*_disk` column for a file written to $disk (default: the current one). */
    public static function column(?string $disk = null): ?string
    {
        $disk ??= self::current();

        return $disk === self::LOCAL ? null : $disk;
    }

    /** True for a file kept off the local disk — somewhere a filesystem call would be a network round trip. */
    public static function isRemote(?string $disk): bool
    {
        return filled($disk) && $disk !== self::LOCAL;
    }

    /**
     * The disk a record's file lives on. A disk that is no longer configured falls back to local rather than
     * throwing, so one stale row can never take a whole page or API response down.
     */
    public static function disk(?string $name = null): Cloud
    {
        return Storage::disk(filled($name) && config("filesystems.disks.{$name}") ? $name : self::LOCAL);
    }

    /**
     * Proves a remote disk (S3 or RustFS) can actually be written to — a small object is written and removed
     * again. Goes to the underlying Flysystem driver because Laravel's disk swallows the reason (it only returns false).
     *
     * @throws \Throwable with the storage provider's own message when the credentials, bucket or endpoint are wrong
     */
    public static function probe(string $diskName = self::S3): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        $driver = $disk->getDriver();
        $path = '.eradio-connection-test-'.Str::uuid();

        $driver->write($path, 'ok');
        $driver->delete($path);
    }
}
