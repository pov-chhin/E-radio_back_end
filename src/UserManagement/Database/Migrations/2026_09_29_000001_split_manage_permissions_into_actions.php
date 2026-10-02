<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    /** Resource slug => the action permissions it gets (matches the routes each controller actually exposes). */
    private const RESOURCES = [
        'radio-channels' => ['view', 'create', 'edit', 'delete'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'tags' => ['view', 'create', 'edit', 'delete'],
        'artists' => ['view', 'create', 'edit', 'delete'],
        'programs' => ['view', 'create', 'edit', 'delete'],
        'media-content' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        // No edit route: a permission's slug can't be changed once routes depend on it.
        'permissions' => ['view', 'create', 'delete'],
        // No edit route: admin users are registered or removed, not renamed here.
        'users' => ['view', 'create', 'delete'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::RESOURCES as $resource => $actions) {
            $oldPermissionId = DB::table('permissions')->where('slug', "manage-{$resource}")->value('id');

            $roleIds = $oldPermissionId
                ? DB::table('permission_role')->where('permission_id', $oldPermissionId)->pluck('role_id')
                : collect();

            foreach ($actions as $action) {
                $slug = "{$action}-{$resource}";

                $newPermissionId = DB::table('permissions')->where('slug', $slug)->value('id')
                    ?? DB::table('permissions')->insertGetId([
                        'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
                        'slug' => $slug,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->updateOrInsert([
                        'permission_id' => $newPermissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }

            // Cascades to permission_role, carrying over to the new action permissions above.
            if ($oldPermissionId) {
                DB::table('permissions')->where('id', $oldPermissionId)->delete();
            }
        }
    }

    public function down(): void
    {
        $now = now();

        foreach (self::RESOURCES as $resource => $actions) {
            $newSlugs = array_map(fn ($action) => "{$action}-{$resource}", $actions);
            $newPermissionIds = DB::table('permissions')->whereIn('slug', $newSlugs)->pluck('id');

            $roleIds = $newPermissionIds->isNotEmpty()
                ? DB::table('permission_role')->whereIn('permission_id', $newPermissionIds)->pluck('role_id')->unique()
                : collect();

            $oldSlug = "manage-{$resource}";
            $oldPermissionId = DB::table('permissions')->where('slug', $oldSlug)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => Str::of($oldSlug)->replace('-', ' ')->title()->toString(),
                    'slug' => $oldSlug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $oldPermissionId,
                    'role_id' => $roleId,
                ]);
            }

            // Cascades to permission_role.
            DB::table('permissions')->whereIn('slug', $newSlugs)->delete();
        }
    }
};
