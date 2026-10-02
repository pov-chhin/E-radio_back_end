<?php

namespace Src\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class AzuraCastSetting extends Model
{
    protected $table = 'azuracast_settings';

    protected $fillable = ['url', 'api_key', 'station_id', 'timeout'];

    protected function casts(): array
    {
        return ['timeout' => 'integer'];
    }

    /**
     * Pushes this row's values into the services.azuracast config, so
     * AzuraCastService and everything built on it (RadioService, the Airwave
     * dashboard) read the admin-edited connection without themselves knowing
     * settings are stored in the database.
     */
    public function applyToConfig(): void
    {
        config([
            'services.azuracast.url' => $this->url,
            'services.azuracast.api_key' => $this->api_key,
            'services.azuracast.station_id' => $this->station_id,
            'services.azuracast.timeout' => $this->timeout,
        ]);
    }
}
