<?php

namespace Src\Catalog\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Program extends Model
{
    protected $fillable = ['category_id', 'tag_id', 'name', 'slug', 'description', 'cover_image', 'cover_image_disk', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The podcast type: one of the tags directly under the top-level Podcast tag. */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function mediaContents(): HasMany
    {
        return $this->hasMany(MediaContent::class);
    }

    /** The episodes the mobile API may show (see MediaContent::scopePubliclyVisible); order at the call site. */
    public function episodes(): HasMany
    {
        return $this->hasMany(MediaContent::class)->publiclyVisible();
    }

    /** The newest visible episode — its artist is the show's host, its picture the cover when the show has none. */
    public function latestEpisode(): HasOne
    {
        return $this->hasOne(MediaContent::class)
            ->ofMany(['published_at' => 'max', 'id' => 'max'], fn ($query) => $query->publiclyVisible());
    }

    /** `cover_image` stores a path on the disk named in `cover_image_disk` (null = local public) or, for older rows, a plain external URL — resolve either to something an <img> can use. */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if (blank($this->cover_image)) {
                return null;
            }

            return str_starts_with($this->cover_image, 'http://') || str_starts_with($this->cover_image, 'https://')
                ? $this->cover_image
                : MediaStorage::url($this->cover_image, $this->cover_image_disk);
        });
    }
}
