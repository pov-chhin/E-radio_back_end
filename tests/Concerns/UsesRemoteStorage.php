<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;
use Src\Setting\Models\MinioSetting;
use Src\Setting\Models\RustfsSetting;
use Src\Setting\Models\StorageSetting;

/** Lets a test choose a remote storage the way Settings → Storage would, without anything leaving the test. */
trait UsesRemoteStorage
{
    /** @return array<string, array{string, string}> storage type chosen in the settings => the disk it uploads to */
    public static function remoteStorages(): array
    {
        return [
            'S3-compatible' => ['s3', 'media_s3'],
            'RustFS' => ['rustfs', 'media_rustfs'],
            'MinIO' => ['minio', 'media_minio'],
        ];
    }

    /**
     * Selects the remote storage the way the settings page would and swaps its disk for an in-memory double, so
     * nothing leaves the test. The config is applied first: applying rebuilds the disk, so the double goes in after.
     */
    private function useRemoteStorage(string $driver, string $disk): void
    {
        match ($driver) {
            's3' => StorageSetting::firstOrFail()->update(['s3_key' => 'k', 's3_secret' => 's', 's3_bucket' => 'b', 's3_region' => 'auto']),
            'rustfs' => RustfsSetting::firstOrFail()->update(['s3_key' => 'k', 's3_secret' => 's']),
            'minio' => MinioSetting::firstOrFail()->update(['s3_key' => 'k', 's3_secret' => 's', 's3_endpoint' => 'http://minio.example.com:9000']),
        };
        StorageSetting::firstOrFail()->update(['driver' => $driver]);

        StorageSetting::firstOrFail()->applyToConfig();
        Storage::fake($disk, ['url' => 'https://cdn.example.com']);
        config(["filesystems.disks.{$disk}.temporary_urls" => false]);
    }

    private function backToLocalStorage(): void
    {
        StorageSetting::firstOrFail()->update(['driver' => 'local']);
        StorageSetting::firstOrFail()->applyToConfig();
    }
}
