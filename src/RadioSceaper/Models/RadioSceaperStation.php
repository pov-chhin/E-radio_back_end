<?php

namespace Src\RadioSceaper\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class RadioSceaperStation extends Model
{
    protected $fillable = ['name', 'description', 'stream_url', 'cover', 'cover_disk', 'bitrate', 'votes', 'tags', 'radio_browser_uuid', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['bitrate' => 'integer', 'votes' => 'integer', 'is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    /** The order listeners see, and the admin list shows: channels with a sort order first (1, 2, 3…), then the ones left at 0 below them; each group by id (oldest first). */
    public function scopeInListenerOrder(Builder $query): void
    {
        $query->orderByRaw('sort_order = 0')->orderBy('sort_order')->orderBy('id');
    }

    /** 'draft' (no stream URL yet), 'hidden' (has a URL, not published), or 'published'. */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                blank($this->stream_url) => 'draft',
                $this->is_published => 'published',
                default => 'hidden',
            },
        );
    }

    /** `cover` stores a path on the disk named in `cover_disk` (null = local public) or, for scraped stations, may one day be an external URL — resolve either to something an <img> can use. */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if (blank($this->cover)) {
                return null;
            }

            return str_starts_with($this->cover, 'http://') || str_starts_with($this->cover, 'https://')
                ? $this->cover
                : MediaStorage::url($this->cover, $this->cover_disk);
        });
    }
}
