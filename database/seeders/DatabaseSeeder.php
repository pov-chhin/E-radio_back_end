<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\User\User;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Bootstraps a fresh install into a usable state: every permission any
     * route declares (via `permissions:sync`), an "Admin" role holding all
     * of them, and one admin account to log in with. Safe to re-run —
     * additive only, never touches an admin that already exists.
     */
    public function run(): void
    {
        Artisan::call('permissions:sync');

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $adminRole->permissions()->syncWithoutDetaching(Permission::pluck('id'));

        if (User::count() > 0) {
            $this->command?->info('Admin account(s) already exist — skipped creating a new one.');

            return;
        }

        $password = Str::password(16);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@erado.com',
            'password' => Hash::make($password),
        ]);
        $admin->roles()->sync([$adminRole->id]);

        $this->command?->info('Created an admin account you can log in with at /login:');
        $this->command?->info('  email:    admin@erado.com');
        $this->command?->info("  password: {$password}");
        $this->command?->warn('Change this password after your first login.');
    }
}
