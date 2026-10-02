<?php

namespace Src\Catalog\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Artist extends Model
{
    protected $fillable = [
        'name', 'slug', 'stage_name', 'photo', 'cover', 'bio',
        'verified', 'social_links', 'status',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'social_links' => 'array',
        ];
    }

    public function mediaContents(): HasMany
    {
        return $this->hasMany(MediaContent::class);
    }

    /** `photo`/`cover` store a local `public` disk path (uploads) or, for older rows, a plain external URL — resolve either to something an <img> can use. */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->photo));
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->cover));
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : Storage::disk('public')->url($value);
    }
}
