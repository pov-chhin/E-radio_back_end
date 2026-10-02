<?php

namespace Src\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class RadioScraperSetting extends Model
{
    protected $fillable = ['enabled', 'country'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /**
     * Pushes this row's values into the services.radio_browser config, so
     * Src\RadioSceaper\RadioSceaper and everything built on it (RadioService,
     * radio:scrape, radio:sync) read the admin-edited values without
     * themselves knowing settings are stored in the database.
     */
    public function applyToConfig(): void
    {
        config([
            'services.radio_browser.enabled' => $this->enabled,
            'services.radio_browser.country' => $this->country,
        ]);
    }
}
