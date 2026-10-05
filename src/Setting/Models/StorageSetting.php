<?php

namespace Src\Setting\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Src\Setting\Models\Concerns\HasS3Disk;

class StorageSetting extends Model
{
    use HasS3Disk;

    /** The storage types offered as radio buttons: the local disk, S3-compatible storage, or the RustFS tab's connection. */
    public const DRIVERS = ['local', 's3', 'rustfs'];

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

    /**
     * Pushes this row's values into config, so MediaContentController and ProcessMediaContentAudio store new
     * uploads where the admin chose without themselves knowing settings live in the database. Existing files
     * already stored aren't moved — each record remembers the disk its files are on. The RustFS connection is
     * applied here too, so one call refreshes everything storage needs (the queue worker relies on that).
     */
    public function applyToConfig(): void
    {
        $s3 = $this->applyS3DiskToConfig();
        $rustfs = $this->applyRustfsToConfig();

        config([
            'services.media_storage.image_directory' => $this->image_directory,
            'services.media_storage.audio_directory' => $this->audio_directory,
            // Falls back to local while the chosen storage is unusable, so an upload never goes nowhere.
            'services.media_storage.disk' => match (true) {
                $this->usesS3() && $s3 !== null => MediaStorage::S3,
                $this->usesRustfs() && $rustfs !== null => MediaStorage::RUSTFS,
                default => MediaStorage::LOCAL,
            },
        ]);
    }

    /** @return array<string, mixed>|null the RustFS disk definition, or null while it is not configured */
    private function applyRustfsToConfig(): ?array
    {
        try {
            return RustfsSetting::first()?->applyToConfig();
        } catch (QueryException) {
            // The table does not exist yet (migrations still running).
            return null;
        }
    }
}
