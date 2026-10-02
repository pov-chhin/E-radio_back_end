<?php

namespace Src\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Src\User\User as AdminUser;

class MediaContent extends Model
{
    protected $fillable = [
        'category_id', 'program_id', 'title', 'slug', 'artist_id', 'author_id', 'description',
        'media_type', 'stream', 'processing_status', 'source_path', 'processing_error',
        'thumbnail', 'duration_seconds', 'play_count', 'favorite_count',
        'is_published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'duration_seconds' => 'integer',
            'play_count' => 'integer',
            'favorite_count' => 'integer',
        ];
    }

    /** What the mobile API may show: published, fully processed and past its publish time. */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('is_published', true)
            ->where('processing_status', 'ready')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /** The admin account credited as the author — defaults to whoever created it. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** `stream`/`thumbnail` store a local `public` disk path (uploads) or a plain external URL — resolve either to something usable directly. */
    protected function streamUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->stream));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->thumbnail));
    }

    /** Size in bytes for locally stored media; external streams have no reliable finite size. */
    protected function streamSize(): Attribute
    {
        return Attribute::make(get: function (): ?int {
            if (blank($this->stream) || str_starts_with($this->stream, 'http://') || str_starts_with($this->stream, 'https://')) {
                return null;
            }

            $disk = Storage::disk('public');

            return $disk->exists($this->stream) ? $disk->size($this->stream) : null;
        });
    }

    protected function streamSizeLabel(): Attribute
    {
        return Attribute::make(get: function (): string {
            $bytes = $this->stream_size;

            if ($bytes === null) {
                return filled($this->stream) ? 'External' : '—';
            }

            if ($bytes < 1024) {
                return $bytes.' B';
            }

            $units = ['KB', 'MB', 'GB', 'TB'];
            $size = $bytes / 1024;
            $unit = 0;

            while ($size >= 1024 && $unit < count($units) - 1) {
                $size /= 1024;
                $unit++;
            }

            return number_format($size, 1).' '.$units[$unit];
        });
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

    /** 'private' (not published), 'scheduled' (published, but published_at is still in the future), or 'public'. */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                ! $this->is_published => 'private',
                $this->published_at?->isFuture() => 'scheduled',
                default => 'public',
            },
        );
    }

    /** `published_at` is stored in app.timezone (UTC); the CMS displays it in the admin's own timezone. */
    protected function publishedAtLocal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->published_at?->copy()->setTimezone(config('app.admin_timezone')),
        );
    }
}
