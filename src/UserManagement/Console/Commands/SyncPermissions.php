<?php

namespace Src\UserManagement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Src\UserManagement\Models\Permission;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = "Create a Permission row for every permission:<slug> route middleware that doesn't have one yet";

    public function handle(): int
    {
        $slugs = collect(Route::getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => str_starts_with($middleware, 'permission:'))
            ->map(fn ($middleware) => Str::after($middleware, 'permission:'))
            ->unique()
            ->sort()
            ->values();

        $created = 0;

        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::of($slug)->replace('-', ' ')->title()->toString()]
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("Created: {$permission->name} ({$slug})");
            }
        }

        $this->info($created > 0
            ? "Synced {$slugs->count()} route permission(s) — {$created} new."
            : "Synced {$slugs->count()} route permission(s) — nothing new.");

        return self::SUCCESS;
    }
}
