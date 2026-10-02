<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin Users gained an actual edit-type action (activate/deactivate), so it needs the `edit-users`
 * permission the 2026-09-29 manage-split deliberately left out (there was no edit route yet). Any role
 * that already manages users (view/create/delete) gets it too, so existing access doesn't shrink.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->where('slug', 'edit-users')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'Edit Users',
                'slug' => 'edit-users',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $existingUserPermissionIds = DB::table('permissions')
            ->whereIn('slug', ['view-users', 'create-users', 'delete-users'])
            ->pluck('id');

        $roleIds = DB::table('permission_role')
            ->whereIn('permission_id', $existingUserPermissionIds)
            ->pluck('role_id')
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'edit-users')->delete();
    }
};
