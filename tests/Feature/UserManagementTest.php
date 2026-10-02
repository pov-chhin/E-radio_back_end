<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\User\User;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = $this->createAdmin([
            'view-roles', 'create-roles', 'edit-roles', 'delete-roles',
            'view-permissions', 'create-permissions', 'delete-permissions',
            'view-users', 'create-users', 'edit-users', 'delete-users',
        ]);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_it_creates_a_permission(): void
    {
        $this->post('/admin/permissions', [
            'name' => 'Manage Songs',
            'slug' => 'manage-songs',
        ])->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseHas('permissions', ['slug' => 'manage-songs']);
    }

    public function test_it_creates_a_role_with_permissions(): void
    {
        $permission = Permission::create(['name' => 'Manage Songs', 'slug' => 'manage-songs']);

        $this->post('/admin/roles', [
            'name' => 'Editor',
            'slug' => 'editor',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('slug', 'editor')->firstOrFail();
        $this->assertTrue($role->permissions->contains($permission));
    }

    public function test_it_updates_a_roles_permissions(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $keep = Permission::create(['name' => 'Manage Songs', 'slug' => 'manage-songs']);
        $drop = Permission::create(['name' => 'Manage Videos', 'slug' => 'manage-videos']);
        $role->permissions()->sync([$keep->id, $drop->id]);

        $this->put("/admin/roles/{$role->id}", [
            'name' => 'Editor',
            'slug' => 'editor',
            'permissions' => [$keep->id],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertTrue($role->permissions->contains($keep));
        $this->assertFalse($role->permissions->contains($drop));
    }

    public function test_it_registers_a_user_with_roles(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        $this->post('/admin/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
            'roles' => [$role->id],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_has_permission_checks_through_roles(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $permission = Permission::create(['name' => 'Manage Songs', 'slug' => 'manage-songs']);
        $role->permissions()->sync([$permission->id]);
        $this->admin->roles()->sync([$role->id]);

        $this->assertTrue($this->admin->fresh()->hasPermission('manage-songs'));
        $this->assertFalse($this->admin->fresh()->hasPermission('manage-users'));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->delete("/admin/users/{$this->admin->id}")->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'type' => 'admin']);
    }

    public function test_it_toggles_a_users_active_status(): void
    {
        $user = User::create(['name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => 'password']);
        $this->assertTrue($user->fresh()->is_active);

        $this->patch("/admin/users/{$user->id}/toggle-active")->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->patch("/admin/users/{$user->id}/toggle-active")->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $this->patch("/admin/users/{$this->admin->id}/toggle-active")->assertRedirect();

        $this->assertTrue($this->admin->fresh()->is_active);
    }
}
