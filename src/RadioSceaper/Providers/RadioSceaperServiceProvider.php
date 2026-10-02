<?php

namespace Src\RadioSceaper\Providers;

use Illuminate\Support\ServiceProvider;
use Src\RadioSceaper\Console\Commands\ScrapeRadioStations;
use Src\RadioSceaper\Console\Commands\SyncRadioStations;

class RadioSceaperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScrapeRadioStations::class,
                SyncRadioStations::class,
            ]);
        }
    }
}
