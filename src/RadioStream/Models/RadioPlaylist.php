<?php

namespace Src\RadioStream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioPlaylist extends Model
{
    protected $fillable = ['radio_station_id', 'name', 'type', 'weight', 'is_enabled'];
    protected function casts(): array { return ['is_enabled' => 'boolean', 'weight' => 'integer']; }
    public function station(): BelongsTo { return $this->belongsTo(RadioStation::class, 'radio_station_id'); }
}
