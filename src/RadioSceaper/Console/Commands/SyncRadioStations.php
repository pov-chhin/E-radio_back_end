<?php

namespace Src\RadioSceaper\Console\Commands;

use Illuminate\Console\Command;
use Src\RadioSceaper\Services\RadioChannelSync;

class SyncRadioStations extends Command
{
    protected $signature = 'radio:sync {--country= : ISO country code to sync against radio-browser.info}';

    protected $description = 'Add new radio_sceaper_stations records and refresh stream URLs from radio-browser.info';

    public function handle(RadioChannelSync $sync): int
    {
        $countryCode = strtoupper((string) ($this->option('country') ?: config('services.radio_browser.country', 'KH')));
        $result = $sync->sync($countryCode);

        if ($result === null) {
            $this->error("radio-browser.info request failed for {$countryCode}.");

            return self::FAILURE;
        }

        foreach ($result['added_channels'] as $channel) {
            $this->line("  [+] [{$channel['id']}] {$channel['name']} -> {$channel['stream_url']}");
        }

        foreach ($result['updated_channels'] as $channel) {
            $this->line("  [~] [{$channel['id']}] {$channel['name']} -> {$channel['stream_url']}");
        }

        $this->info("Done — added {$result['added']}, updated {$result['updated']} (from {$result['stations']} live station(s) for {$countryCode}).");

        return self::SUCCESS;
    }
}
