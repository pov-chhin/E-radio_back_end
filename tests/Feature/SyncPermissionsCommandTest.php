<?php

namespace Tests\Feature;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Src\UserManagement\Models\Permission;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_permission_for_a_route_that_declares_one(): void
    {
        Route::get('/admin/__qa_temp', fn () => 'ok')->middleware(['web', 'auth:admin', 'permission:qa-temp-thing']);

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['slug' => 'qa-temp-thing', 'name' => 'Qa Temp Thing']);
    }

    public function test_it_picks_up_every_permission_already_declared_by_the_real_app_routes(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();

        $slugs = ['manage-settings'];
        foreach (['radio-channels', 'categories', 'tags', 'artists', 'programs', 'media-content', 'roles', 'users'] as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $slugs[] = "{$action}-{$resource}";
            }
        }
        foreach (['permissions'] as $resource) {
            foreach (['view', 'create', 'delete'] as $action) {
                $slugs[] = "{$action}-{$resource}";
            }
        }

        foreach ($slugs as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }
    }

    public function test_it_does_not_duplicate_an_existing_permission(): void
    {
        Route::get('/admin/__qa_temp', fn () => 'ok')->middleware(['web', 'auth:admin', 'permission:qa-temp-thing']);

        $this->artisan('permissions:sync');
        $this->artisan('permissions:sync');

        $this->assertSame(1, Permission::where('slug', 'qa-temp-thing')->count());
    }

    public function test_migrating_automatically_syncs_permissions(): void
    {
        Route::get('/admin/__qa_temp', fn () => 'ok')->middleware(['web', 'auth:admin', 'permission:qa-temp-thing']);

        Event::dispatch(new MigrationsEnded('up'));

        $this->assertDatabaseHas('permissions', ['slug' => 'qa-temp-thing']);
    }
}
