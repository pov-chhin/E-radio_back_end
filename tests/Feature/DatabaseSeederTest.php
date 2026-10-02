<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\User\User;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_role_with_every_synced_permission(): void
    {
        $this->seed();

        $role = Role::where('slug', 'admin')->firstOrFail();
        $permissionCount = Permission::count();

        $this->assertGreaterThan(0, $permissionCount);
        $this->assertSame($permissionCount, $role->permissions()->count());
    }

    public function test_it_creates_a_working_admin_account(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));

        // Every permission the app currently knows about should be usable through this account.
        foreach (Permission::pluck('slug') as $slug) {
            $this->assertTrue($admin->hasPermission($slug), "Seeded admin is missing permission: {$slug}");
        }
    }

    public function test_it_does_not_create_a_second_admin_when_one_already_exists(): void
    {
        $this->seed();
        $this->assertSame(1, User::count());

        $this->seed();

        $this->assertSame(1, User::count());
    }

    public function test_reseeding_still_keeps_the_admin_role_permissions_up_to_date(): void
    {
        $this->seed();
        $role = Role::where('slug', 'admin')->firstOrFail();
        $role->permissions()->detach();
        $this->assertSame(0, $role->permissions()->count());

        $this->seed();

        $this->assertSame(Permission::count(), $role->fresh()->permissions()->count());
    }
}
