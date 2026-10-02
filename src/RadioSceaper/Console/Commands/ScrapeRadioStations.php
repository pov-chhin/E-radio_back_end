<?php

namespace Src\RadioSceaper\Console\Commands;

use Illuminate\Console\Command;
use Src\RadioSceaper\RadioSceaper;

class ScrapeRadioStations extends Command
{
    protected $signature = 'radio:scrape {--country= : ISO country code to query on radio-browser.info}';

    protected $description = 'List live, working radio-browser.info stations for a country';

    public function handle(RadioSceaper $scraper): int
    {
        $countryCode = strtoupper((string) ($this->option('country') ?: config('services.radio_browser.country', 'KH')));
        $stations = $scraper->get($countryCode);

        if ($stations === null) {
            $this->error("radio-browser.info request failed for {$countryCode}.");

            return self::FAILURE;
        }

        foreach ($stations as $station) {
            $this->line("  [{$station['votes']}] {$station['name']} -> {$station['stream_url']}");
        }

        $this->info('Found '.count($stations)." live station(s) for {$countryCode}.");

        return self::SUCCESS;
    }
}
