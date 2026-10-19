<?php

namespace Src\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Console\Commands\FetchYoutubeAudioCommand;
use Src\Catalog\Console\Commands\ImportAmbientCardImagesCommand;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchYoutubeAudioCommand::class,
                ImportAmbientCardImagesCommand::class,
            ]);
        }
    }
}
