<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Src\CmsAdmin\Models\User as Customer;
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

    public function test_the_permissions_page_is_hidden_from_the_sidebar_but_still_opens(): void
    {
        $sidebar = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('admin.permissions.index').'"', $sidebar);
        // The neighbours are still there.
        $this->assertStringContainsString('href="'.route('admin.roles.index').'"', $sidebar);

        $this->get('/admin/permissions')->assertOk();
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

    private function teammate(array $attributes = []): User
    {
        return User::create([
            'name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => 'old-password',
            ...$attributes,
        ]);
    }

    public function test_the_users_list_has_an_edit_button_for_each_user(): void
    {
        $user = $this->teammate();

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('href="'.route('admin.users.edit', $user).'"', false)
            ->assertSee('href="'.route('admin.users.edit', $this->admin).'"', false);
    }

    public function test_the_edit_page_shows_the_users_details_and_roles(): void
    {
        $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $viewer = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $user = $this->teammate(['locale' => 'km']);
        $user->roles()->sync([$editor->id]);

        $html = $this->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('Edit User')
            ->assertSee('value="Teammate"', false)
            ->assertSee('value="teammate@example.com"', false)
            ->assertSee('Leave blank to keep the current password.')
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="km"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="'.$editor->id.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$viewer->id.'"\s+checked/', $html);
    }

    public function test_it_updates_a_user_and_their_roles(): void
    {
        $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $viewer = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $user = $this->teammate();
        $user->roles()->sync([$editor->id]);

        $this->put("/admin/users/{$user->id}", [
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'locale' => 'km',
            'roles' => [$viewer->id],
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('status', 'User updated.');

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertSame('km', $user->locale);
        $this->assertSame([$viewer->id], $user->roles()->pluck('roles.id')->all());
    }

    public function test_a_blank_password_keeps_the_current_one(): void
    {
        $user = $this->teammate();

        $this->put("/admin/users/{$user->id}", ['name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => ''])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_a_new_password_replaces_the_old_one(): void
    {
        $user = $this->teammate();

        $this->put("/admin/users/{$user->id}", ['name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => 'brand-new-password'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_a_short_new_password_is_rejected(): void
    {
        $user = $this->teammate();

        $this->put("/admin/users/{$user->id}", ['name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_email_may_be_kept_but_not_taken_from_someone_else(): void
    {
        $user = $this->teammate();

        $this->put("/admin/users/{$user->id}", ['name' => 'Teammate', 'email' => 'teammate@example.com'])
            ->assertSessionHasNoErrors();

        $this->put("/admin/users/{$user->id}", ['name' => 'Teammate', 'email' => $this->admin->email])
            ->assertSessionHasErrors('email');

        $this->assertSame('teammate@example.com', $user->fresh()->email);
    }

    public function test_admin_cannot_change_their_own_roles(): void
    {
        $other = Role::create(['name' => 'Other', 'slug' => 'other']);
        $before = $this->admin->roles()->pluck('roles.id')->sort()->values()->all();
        $this->assertNotEmpty($before);

        $this->put("/admin/users/{$this->admin->id}", [
            'name' => 'Admin Renamed',
            'email' => $this->admin->email,
            'roles' => [$other->id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('Admin Renamed', $this->admin->fresh()->name);
        $this->assertSame($before, $this->admin->roles()->pluck('roles.id')->sort()->values()->all());
    }

    public function test_editing_your_own_account_shows_the_roles_locked(): void
    {
        $this->get("/admin/users/{$this->admin->id}/edit")
            ->assertOk()
            ->assertSee("You can't change your own roles.")
            ->assertSee('disabled', false);
    }

    public function test_editing_needs_the_edit_users_permission(): void
    {
        $user = $this->teammate();

        // Its own role: createAdmin() shares one role between admins, which setUp already filled with edit-users.
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => 'password123']);
        $role = Role::create(['name' => 'Users Viewer', 'slug' => 'users-viewer']);
        $role->permissions()->sync([Permission::where('slug', 'view-users')->firstOrFail()->id]);
        $viewer->roles()->sync([$role->id]);
        $this->actingAs($viewer, 'admin');

        $this->get('/admin/users')->assertOk();
        $this->get("/admin/users/{$user->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$user->id}", ['name' => 'Hacked', 'email' => 'teammate@example.com'])->assertForbidden();

        $this->assertSame('Teammate', $user->fresh()->name);
    }

    public function test_a_customer_account_cannot_be_edited_here(): void
    {
        $customer = Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => 'password123']);

        $this->get("/admin/users/{$customer->id}/edit")->assertNotFound();
        $this->put("/admin/users/{$customer->id}", ['name' => 'Jane', 'email' => 'jane@example.com'])->assertNotFound();
    }
}
