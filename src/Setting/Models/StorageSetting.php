<?php

namespace Src\Setting\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Src\Setting\Models\Concerns\HasS3Disk;

class StorageSetting extends Model
{
    use HasS3Disk;

    /** The storage types offered as radio buttons: the local disk, S3-compatible storage, or the RustFS / MinIO tab's connection. */
    public const DRIVERS = ['local', 's3', 'rustfs', 'minio'];

    protected $fillable = [
        'driver', 'image_directory', 'audio_directory',
        's3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url', 's3_use_path_style_endpoint',
    ];

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
        return MediaStorage::S3;
    }

    public function usesS3(): bool
    {
        return $this->driver === 's3';
    }

    public function usesRustfs(): bool
    {
        return $this->driver === 'rustfs';
    }

    public function usesMinio(): bool
    {
        return $this->driver === 'minio';
    }

    /** The remote disk the chosen storage type writes to, or null for the local disk. */
    public function remoteDisk(): ?string
    {
        return match ($this->driver) {
            's3' => MediaStorage::S3,
            'rustfs' => MediaStorage::RUSTFS,
            'minio' => MediaStorage::MINIO,
            default => null,
        };
    }

    /**
     * Pushes this row's values into config, so MediaContentController and ProcessMediaContentAudio store new
     * uploads where the admin chose without themselves knowing settings live in the database. Existing files
     * already stored aren't moved — each record remembers the disk its files are on. The RustFS and MinIO
     * connections are applied here too, so one call refreshes everything storage needs (the queue worker relies on that).
     */
    public function applyToConfig(): void
    {
        $disks = [
            MediaStorage::S3 => $this->applyS3DiskToConfig(),
            MediaStorage::RUSTFS => $this->applyConnectionToConfig(RustfsSetting::class),
            MediaStorage::MINIO => $this->applyConnectionToConfig(MinioSetting::class),
        ];
        $chosen = $this->remoteDisk();

        config([
            'services.media_storage.image_directory' => $this->image_directory,
            'services.media_storage.audio_directory' => $this->audio_directory,
            // Falls back to local while the chosen storage is unusable, so an upload never goes nowhere.
            'services.media_storage.disk' => $chosen !== null && $disks[$chosen] !== null ? $chosen : MediaStorage::LOCAL,
        ]);
    }

    /**
     * @param  class-string<RustfsSetting|MinioSetting>  $model
     * @return array<string, mixed>|null the disk definition, or null while that connection is not configured
     */
    private function applyConnectionToConfig(string $model): ?array
    {
        try {
            return $model::first()?->applyToConfig();
        } catch (QueryException) {
            // The table does not exist yet (migrations still running).
            return null;
        }
    }
}
