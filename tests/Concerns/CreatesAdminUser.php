<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\User\User;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;

trait CreatesAdminUser
{
    protected function createAdmin(array $permissionSlugs = []): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => Str::random(8).'@example.com',
            'password' => Hash::make('password'),
        ]);

        if ($permissionSlugs !== []) {
            $permissions = collect($permissionSlugs)->map(
                fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])
            );

            $role = Role::firstOrCreate(['slug' => 'test-admin'], ['name' => 'Test Admin']);
            $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }
}
