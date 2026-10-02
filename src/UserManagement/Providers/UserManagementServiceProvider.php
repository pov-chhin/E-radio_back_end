<?php

namespace Src\UserManagement\Providers;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\UserManagement\Console\Commands\SyncPermissions;

class UserManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'user-management');
        Route::middleware(['web', 'auth:admin'])->group(__DIR__.'/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPermissions::class,
            ]);

            // Every route already declares which permission:<slug> it needs;
            // run the sync automatically whenever `migrate` brings the schema
            // up to date, so a new permission-gated route never needs its own
            // one-off seeding migration.
            Event::listen(MigrationsEnded::class, fn () => Artisan::call('permissions:sync'));
        }
    }
}
