<?php

namespace Src\User\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Src\User\User;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {password} {--name=Admin}';

    protected $description = 'Create a CMS admin user that can log into /admin';

    public function handle(): int
    {
        $validator = Validator::make([
            'email' => $this->argument('email'),
            'password' => $this->argument('password'),
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
        ]);

        $this->info("Admin user created: {$user->email}");

        return self::SUCCESS;
    }
}
