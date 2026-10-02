<?php

namespace Src\RadioStream\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioStream extends Model
{
    protected $fillable = ['radio_station_id', 'protocol', 'codec', 'bitrate', 'url', 'priority', 'is_primary', 'health_status'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'bitrate' => 'integer', 'priority' => 'integer']; }
    public function station(): BelongsTo { return $this->belongsTo(RadioStation::class, 'radio_station_id'); }
}
