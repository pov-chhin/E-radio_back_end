<?php

namespace App\Support;

use Aws\Exception\AwsException;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
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

    /** Names the bucket policy statement that makes the media folders readable, so it can be replaced rather than duplicated. */
    private const PUBLIC_READ_SID = 'EradioPublicRead';

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
     * Return a browser-readable URL for a stored media object.
     *
     * Remote buckets are commonly private. In that case a plain `url()` contains no credentials and the browser
     * receives 403, even though uploads work. S3-compatible disks can instead sign a short-lived GET URL without
     * exposing the access key or secret. Local disks (and adapters without temporary URL support) keep their normal
     * public URL behaviour.
     */
    public static function url(string $path, ?string $diskName = null): string
    {
        if (! self::isRemote($diskName)) {
            // The public disk's configured URL is frozen from APP_URL at boot. Building it from the active request
            // keeps local media usable when the app is reached through a proxy, domain, LAN address, or localhost.
            $root = app()->bound('request')
                ? request()->getSchemeAndHttpHost()
                : rtrim((string) config('app.url'), '/');

            return $root.'/storage/'.ltrim($path, '/');
        }

        $disk = self::disk($diskName);

        if (config("filesystems.disks.{$diskName}.temporary_urls", true)) {
            try {
                return $disk->temporaryUrl($path, now()->addHours(6));
            } catch (\Throwable) {
                // Some custom/fake adapters do not implement temporary URLs; a public bucket URL is still valid.
            }
        }

        return $disk->url($path);
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

    /**
     * Proves a remote disk's files can be read the way the browser and the mobile app read them — an anonymous GET
     * of the public URL — because both load images and audio straight from the bucket. A small file is written into
     * the image folder, fetched and removed again. A bucket that accepts uploads can still refuse every download
     * (403), which shows up as broken images and mp3 links.
     *
     * @return string|null why the files cannot be read, or null when they can
     */
    public static function publicReadError(string $diskName): ?string
    {
        $disk = Storage::disk($diskName);
        $path = trim(config('services.media_storage.image_directory'), '/').'/.eradio-public-read-test-'.Str::uuid();
        $url = null;

        try {
            if (! $disk->put($path, 'ok')) {
                return __('A test file could not be written to check public access.');
            }

            $url = $disk->url($path);
            $response = Http::timeout(10)->connectTimeout(5)->get($url);

            if (! $response->successful()) {
                return __('Files are not publicly readable: fetching one without signing in gives HTTP :status (:url). Images and audio will show as broken links.', ['status' => $response->status(), 'url' => $url]);
            }

            // A web page answering 200 (a storage console, a proxy's catch-all) is not the file.
            return $response->body() === 'ok'
                ? null
                : __('That address answers, but not with the stored file (:url returned :type). Check the endpoint and Public URL point at the files themselves — not at a web console page.', ['url' => $url, 'type' => $response->header('Content-Type')]);
        } catch (\Throwable $exception) {
            $reason = Str::limit($exception->getMessage(), 160);

            return $url
                ? __('Could not check public access by fetching :url: :reason', ['url' => $url, 'reason' => $reason])
                : __('Could not check public access: :reason', ['reason' => $reason]);
        } finally {
            try {
                $disk->delete($path);
            } catch (\Throwable) {
                // Nothing to clean up if the file never made it.
            }
        }
    }

    /**
     * Lets anyone read — and only read — the image and audio folders of a remote disk's bucket, by adding a
     * bucket policy statement. Nothing else in the bucket is touched: statements already in the policy are kept,
     * and only this app's own (by Sid) is replaced.
     *
     * @throws \Throwable with the storage provider's own message, e.g. when the access key may not change policies
     */
    public static function makePublic(string $diskName): void
    {
        $client = Storage::disk($diskName)->getClient();
        $bucket = config("filesystems.disks.{$diskName}.bucket");

        try {
            $policy = json_decode((string) $client->getBucketPolicy(['Bucket' => $bucket])['Policy'], true) ?: [];
        } catch (AwsException $exception) {
            if ($exception->getAwsErrorCode() !== 'NoSuchBucketPolicy') {
                throw $exception;
            }

            $policy = [];
        }

        // A policy's Statement may be a single object instead of a list.
        $statements = $policy['Statement'] ?? [];
        $statements = isset($statements['Effect']) ? [$statements] : $statements;
        $statements = array_values(array_filter($statements, fn (array $statement) => ($statement['Sid'] ?? null) !== self::PUBLIC_READ_SID));

        $statements[] = [
            'Sid' => self::PUBLIC_READ_SID,
            'Effect' => 'Allow',
            'Principal' => ['AWS' => ['*']],
            'Action' => ['s3:GetObject'],
            'Resource' => collect([config('services.media_storage.image_directory'), config('services.media_storage.audio_directory')])
                ->map(fn (string $folder) => "arn:aws:s3:::{$bucket}/".trim($folder, '/').'/*')
                ->unique()
                ->values()
                ->all(),
        ];

        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode(['Version' => '2012-10-17', ...$policy, 'Statement' => $statements]),
        ]);
    }
}
