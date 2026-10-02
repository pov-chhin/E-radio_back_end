<?php

namespace Src\User\Providers;

use Illuminate\Support\ServiceProvider;
use Src\User\Console\Commands\CreateAdminUser;

class UserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAdminUser::class,
            ]);
        }
    }
}
