<?php

namespace Src\RadioStream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadioStation extends Model
{
    protected $fillable = ['name', 'slug', 'frequency', 'description', 'status', 'azuracast_id'];

    public function streams(): HasMany { return $this->hasMany(RadioStream::class); }
    public function playlists(): HasMany { return $this->hasMany(RadioPlaylist::class); }
    public function schedules(): HasMany { return $this->hasMany(RadioSchedule::class); }
}
