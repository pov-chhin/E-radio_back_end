<?php

namespace Src\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Support\MediaStorage;
use Src\User\User as AdminUser;

class MediaContent extends Model
{
    protected $fillable = [
        'category_id', 'program_id', 'title', 'slug', 'author_id', 'description',
        'media_type', 'stream', 'stream_disk', 'processing_status', 'source_path', 'source_url', 'processing_error',
        'thumbnail', 'thumbnail_disk', 'duration_seconds', 'play_count', 'favorite_count',
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

    /** Everyone credited on it, by name. */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->orderBy('artists.name');
    }

    /** Who is credited, by stage name where an artist has one, joined for a list: "A, B". */
    protected function artistNames(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->artists
            ->map(fn (Artist $artist) => $artist->stage_name ?: $artist->name)
            ->implode(', '));
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

    /** `stream`/`thumbnail` store a path on the disk named in `stream_disk`/`thumbnail_disk` (null = local public) or a plain external URL — resolve either to something usable directly. */
    protected function streamUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->stream, $this->stream_disk));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolveMediaUrl($this->thumbnail, $this->thumbnail_disk));
    }

    /** Size in bytes for locally stored media; external streams have no reliable finite size, and asking cloud storage would cost a network round trip per row. */
    protected function streamSize(): Attribute
    {
        return Attribute::make(get: function (): ?int {
            if (blank($this->stream) || str_starts_with($this->stream, 'http://') || str_starts_with($this->stream, 'https://') || MediaStorage::isRemote($this->stream_disk)) {
                return null;
            }

            $disk = MediaStorage::disk($this->stream_disk);

            return $disk->exists($this->stream) ? $disk->size($this->stream) : null;
        });
    }

    /** An uploaded file whose record is still there but whose file is not on the disk any more (storage that did not survive a redeploy). */
    protected function streamMissing(): Attribute
    {
        return Attribute::make(get: fn (): bool => filled($this->stream)
            && ! str_starts_with($this->stream, 'http://')
            && ! str_starts_with($this->stream, 'https://')
            && ! MediaStorage::isRemote($this->stream_disk)
            && ! MediaStorage::disk($this->stream_disk)->exists($this->stream));
    }

    /**
     * 'YouTube' or 'Facebook' when the stream is a link to a video there. Those are web pages, not audio files:
     * the mobile app plays them in the provider's own embedded player instead of its audio player.
     */
    protected function streamProvider(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $host = strtolower((string) parse_url((string) $this->stream, PHP_URL_HOST));
            $isHost = fn (string ...$domains): bool => collect($domains)
                ->contains(fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain));

            return match (true) {
                $host === '' => null,
                $isHost('youtube.com', 'youtube-nocookie.com', 'youtu.be') => 'YouTube',
                $isHost('facebook.com', 'fb.com', 'fb.watch') => 'Facebook',
                default => null,
            };
        });
    }

    protected function streamSizeLabel(): Attribute
    {
        return Attribute::make(get: function (): string {
            $bytes = $this->stream_size;

            if ($bytes === null) {
                return match (true) {
                    blank($this->stream) => '—',
                    $this->stream_provider !== null => $this->stream_provider,
                    MediaStorage::isRemote($this->stream_disk) => 'Cloud storage',
                    default => 'External',
                };
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

    private function resolveMediaUrl(?string $value, ?string $disk): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : MediaStorage::url($value, $disk);
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
