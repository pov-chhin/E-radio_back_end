<?php

namespace Src\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'icon', 'sort_order'];

    /** The name a "New … Media Content" / "Edit … Media Content" title uses: Podcast reads "Podcast Episode" there. */
    public function titleName(): string
    {
        return $this->slug === 'podcast' ? __('Podcast Episode') : $this->name;
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function mediaContents(): HasMany
    {
        return $this->hasMany(MediaContent::class);
    }
}
