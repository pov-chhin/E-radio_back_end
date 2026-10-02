<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['name' => 'Manage Categories', 'slug' => 'manage-categories'],
            ['name' => 'Manage Tags', 'slug' => 'manage-tags'],
            ['name' => 'Manage Programs', 'slug' => 'manage-programs'],
            ['name' => 'Manage Media Content', 'slug' => 'manage-media-content'],
        ];

        $now = now();
        $ids = [];
        foreach ($permissions as $permission) {
            $ids[] = DB::table('permissions')->insertGetId([...$permission, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Grant the bundled `admin` role every catalog permission, same as it
        // already has for every other resource. No-op on installs without it.
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId) {
            DB::table('permission_role')->insert(
                collect($ids)->map(fn ($id) => ['permission_id' => $id, 'role_id' => $adminRoleId])->all()
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', [
            'manage-categories', 'manage-tags', 'manage-programs', 'manage-media-content',
        ])->delete();
    }
};
