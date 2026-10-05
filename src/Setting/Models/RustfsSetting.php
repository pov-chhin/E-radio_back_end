<?php

namespace Src\Setting\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Src\Setting\Models\Concerns\HasS3Disk;

/** The connection to the RustFS server (S3-compatible). Selecting "RustFS" under Settings → Storage uploads to it. */
class RustfsSetting extends Model
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
        return MediaStorage::RUSTFS;
    }

    /** Defines the `media_rustfs` disk from this row; returns it, or null while the credentials are incomplete. */
    public function applyToConfig(): ?array
    {
        return $this->applyS3DiskToConfig();
    }
}
