<?php

namespace Src\Setting\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Src\Setting\Models\Concerns\HasS3Disk;

/** The connection to the MinIO server (S3-compatible). Selecting "MinIO" under Settings → Storage uploads to it. */
class MinioSetting extends Model
{
    use HasS3Disk;

    protected $fillable = ['s3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url', 's3_use_path_style_endpoint'];

    protected $hidden = ['s3_key', 's3_secret'];

    protected function casts(): array
    {
        return [
            's3_key' => 'encrypted',
            's3_secret' => 'encrypted',
            's3_use_path_style_endpoint' => 'boolean',
        ];
    }

    protected function diskName(): string
    {
        return MediaStorage::MINIO;
    }

    protected function envCredential(string $part): ?string
    {
        return config("services.minio.{$part}") ?: null;
    }

    public function envPublicUrl(): ?string
    {
        return config('services.minio.url') ?: null;
    }

    /** Without an address the SDK would talk to Amazon S3, not to the MinIO server. */
    protected function requiresEndpoint(): bool
    {
        return true;
    }

    /** Defines the `media_minio` disk from this row; returns it, or null while the connection is incomplete. */
    public function applyToConfig(): ?array
    {
        return $this->applyS3DiskToConfig();
    }
}
