<?php

namespace Src\CmsAdmin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CmsAdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'cms-admin');

        Route::middleware('web')->group(__DIR__.'/../Routes/auth.php');
        Route::middleware(['web', 'auth:admin'])->group(__DIR__.'/../Routes/web.php');
    }
}
