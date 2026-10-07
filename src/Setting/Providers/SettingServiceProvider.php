<?php

namespace Src\Setting\Providers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Src\Setting\Models\RadioScraperSetting;
use Src\Setting\Models\StorageSetting;

class SettingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'setting');
        Route::middleware(['web', 'auth:admin'])->group(__DIR__.'/../Routes/web.php');

        // Guarded: this runs on every boot, including `migrate` itself
        // before the tables exist on a fresh install.
        try {
            if (Schema::hasTable('radio_scraper_settings')) {
                RadioScraperSetting::first()?->applyToConfig();
            }
            if (Schema::hasTable('storage_settings')) {
                StorageSetting::first()?->applyToConfig();
            }
        } catch (QueryException) {
            // These are optional config overrides. With no reachable database
            // (an image build's `composer install` runs `package:discover`,
            // which boots the app) skip them instead of failing every boot.
        }
    }
}
