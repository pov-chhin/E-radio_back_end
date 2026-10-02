<?php

namespace Src\RadioStream\Providers;

use Src\RadioStream\Contracts\RadioDashboard;
use Src\RadioStream\Services\RadioService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class RadioStreamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RadioDashboard::class, RadioService::class);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth:admin'])->group(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Views', 'radio-stream');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
