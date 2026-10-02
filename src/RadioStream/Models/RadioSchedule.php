<?php

namespace Src\RadioStream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioSchedule extends Model
{
    protected $fillable = ['radio_station_id', 'title', 'host', 'starts_at', 'ends_at', 'days', 'source_type'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'days' => 'array']; }
    public function station(): BelongsTo { return $this->belongsTo(RadioStation::class, 'radio_station_id'); }
}
