<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New resource, no prior permission to split from — grants `view-customers` to any role that already
 * holds `view-users`, since a role trusted to see admin accounts is a reasonable default for seeing app
 * customer accounts too.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->where('slug', 'view-customers')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'View Customers',
                'slug' => 'view-customers',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $viewUsersPermissionId = DB::table('permissions')->where('slug', 'view-users')->value('id');

        $roleIds = $viewUsersPermissionId
            ? DB::table('permission_role')->where('permission_id', $viewUsersPermissionId)->pluck('role_id')
            : collect();

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'view-customers')->delete();
    }
};
