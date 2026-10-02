<?php

namespace Src\RadioSceaper\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RadioSceaperStation extends Model
{
    protected $fillable = ['name', 'description', 'stream_url', 'cover', 'bitrate', 'votes', 'tags', 'radio_browser_uuid', 'is_published'];

    protected function casts(): array
    {
        return ['bitrate' => 'integer', 'votes' => 'integer', 'is_published' => 'boolean'];
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

    /** `cover` stores a local `public` disk path (uploads) or, for scraped stations, may one day be an external URL — resolve either to something an <img> can use. */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if (blank($this->cover)) {
                return null;
            }

            return str_starts_with($this->cover, 'http://') || str_starts_with($this->cover, 'https://')
                ? $this->cover
                : Storage::disk('public')->url($this->cover);
        });
    }
}
