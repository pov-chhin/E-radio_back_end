<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09-29 migration that split each blanket "manage-{resource}"
 * permission into per-action ones. By the time a test runs, RefreshDatabase has already migrated a
 * fresh schema — so the migration's own up()/down() never see a real old-style row there. These tests
 * load the migration file directly and re-run it against a hand-seeded "pre-refactor" role to confirm
 * an existing role's access carries over to the new action permissions instead of silently vanishing.
 */
class SplitManagePermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('src/UserManagement/Database/Migrations/2026_09_29_000001_split_manage_permissions_into_actions.php');
    }

    public function test_a_role_with_the_old_blanket_permission_gets_every_split_action(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Editor', 'slug' => 'qa-editor', 'created_at' => now(), 'updated_at' => now()]);
        $oldPermissionId = DB::table('permissions')->insertGetId(['name' => 'Manage Programs', 'slug' => 'manage-programs', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['permission_id' => $oldPermissionId, 'role_id' => $roleId]);

        $this->migration()->up();

        $this->assertDatabaseMissing('permissions', ['slug' => 'manage-programs']);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $permissionId = DB::table('permissions')->where('slug', "{$action}-programs")->value('id');
            $this->assertNotNull($permissionId, "{$action}-programs should exist");
            $this->assertDatabaseHas('permission_role', ['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function test_a_resource_with_no_edit_route_only_gets_view_create_delete(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Registrar', 'slug' => 'qa-registrar', 'created_at' => now(), 'updated_at' => now()]);
        $oldPermissionId = DB::table('permissions')->insertGetId(['name' => 'Manage Users', 'slug' => 'manage-users', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['permission_id' => $oldPermissionId, 'role_id' => $roleId]);

        $this->migration()->up();

        $this->assertDatabaseMissing('permissions', ['edit-users']);
        foreach (['view', 'create', 'delete'] as $action) {
            $this->assertDatabaseHas('permissions', ['slug' => "{$action}-users"]);
        }
    }

    public function test_a_role_without_the_old_permission_is_left_alone(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Bystander', 'slug' => 'qa-bystander', 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->up();

        $this->assertDatabaseMissing('permission_role', ['role_id' => $roleId]);
    }

    public function test_down_recombines_the_split_actions_back_into_the_old_permission(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Editor', 'slug' => 'qa-editor', 'created_at' => now(), 'updated_at' => now()]);
        $oldPermissionId = DB::table('permissions')->insertGetId(['name' => 'Manage Programs', 'slug' => 'manage-programs', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['permission_id' => $oldPermissionId, 'role_id' => $roleId]);

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $restoredId = DB::table('permissions')->where('slug', 'manage-programs')->value('id');
        $this->assertNotNull($restoredId);
        $this->assertDatabaseHas('permission_role', ['permission_id' => $restoredId, 'role_id' => $roleId]);
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $this->assertDatabaseMissing('permissions', ['slug' => "{$action}-programs"]);
        }
    }
}
