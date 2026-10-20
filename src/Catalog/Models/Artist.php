<?php

namespace Src\Catalog\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = [
        'name', 'slug', 'stage_name', 'photo', 'photo_disk', 'cover', 'cover_disk', 'bio',
        'verified', 'social_links', 'status',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'social_links' => 'array',
        ];
    }

    public function mediaContents(): BelongsToMany
    {
        return $this->belongsToMany(MediaContent::class);
    }

    /** `photo`/`cover` store a path on the disk named in `photo_disk`/`cover_disk` (null = local public) or, for older rows, a plain external URL — resolve either to something an <img> can use. */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->photo, $this->photo_disk));
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->cover, $this->cover_disk));
    }

    private function resolveMediaUrl(?string $value, ?string $disk): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : MediaStorage::url($value, $disk);
    }
}
