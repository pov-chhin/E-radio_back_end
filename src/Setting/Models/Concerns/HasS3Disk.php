<?php

namespace Src\Setting\Models\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Storage;

/**
 * A settings row that holds the connection to one S3-compatible store (Amazon S3, Cloudflare R2, MinIO, RustFS, …)
 * in `s3_*` columns, and turns it into a Laravel filesystem disk named by diskName().
 */
trait HasS3Disk
{
    /** The Laravel disk these credentials define. */
    abstract protected function diskName(): string;

    /**
     * The disk definition, or null until a complete set of credentials is saved — or if the saved ones cannot be
     * decrypted any more (the application key changed), which must never break booting the app.
     *
     * @return array<string, mixed>|null
     */
    public function s3Disk(): ?array
    {
        try {
            [$key, $secret] = [$this->s3_key, $this->s3_secret];
        } catch (DecryptException) {
            [$key, $secret] = [null, null];
        }

        // Keys saved in the settings page win; the environment's pair is only used when none are saved.
        if (blank($key) && blank($secret)) {
            [$key, $secret] = [$this->envCredential('key'), $this->envCredential('secret')];
        }

        if (blank($key) || blank($secret) || blank($this->s3_bucket) || blank($this->s3_region)) {
            return null;
        }

        return [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $this->s3_region,
            'bucket' => $this->s3_bucket,
            'endpoint' => $this->s3_endpoint ?: null,
            'url' => $this->s3_url ?: null,
            'use_path_style_endpoint' => (bool) $this->s3_use_path_style_endpoint,
            // aws-sdk-php >= 3.337 adds a CRC32 checksum to every upload by default; many S3-compatible stores
            // (RustFS, MinIO, Ceph, …) reject it. Only send checksums when the operation requires one.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            // No `visibility`: no ACL header is sent, so it works on buckets with ACLs disabled and on R2/RustFS.
            // Public read access is the bucket's own policy.
            'throw' => false,
            'report' => false,
            'http' => ['connect_timeout' => 5, 'timeout' => 300],
        ];
    }

    /** The access key ('key') or secret ('secret') this row falls back to when none is saved; null for no fallback. */
    protected function envCredential(string $part): ?string
    {
        return null;
    }

    /** True when the environment supplies a complete key and secret to fall back to. */
    public function hasEnvCredentials(): bool
    {
        return filled($this->envCredential('key')) && filled($this->envCredential('secret'));
    }

    /** True when credentials were saved but can no longer be read, so the page can ask for them again. */
    public function s3CredentialsUnreadable(): bool
    {
        return filled($this->getRawOriginal('s3_secret')) && $this->s3Disk() === null && filled($this->s3_bucket);
    }

    /**
     * Defines this row's disk in config and returns its definition (null while incomplete). The disk stays defined
     * whichever storage is selected: files uploaded to it earlier still live there. It is only rebuilt when the
     * credentials actually changed, so Laravel's cached client is reused.
     *
     * @return array<string, mixed>|null
     */
    protected function applyS3DiskToConfig(): ?array
    {
        $disk = $this->s3Disk();
        $name = $this->diskName();
        $fingerprint = $disk ? md5(json_encode($disk)) : null;

        if ($fingerprint !== config("services.media_storage.applied.{$name}")) {
            config(["services.media_storage.applied.{$name}" => $fingerprint]);

            if ($disk) {
                config(["filesystems.disks.{$name}" => $disk]);
            }

            Storage::forgetDisk($name);
        }

        return $disk;
    }
}
