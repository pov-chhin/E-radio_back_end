<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Src\User\User;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login_when_visiting_admin(): void
    {
        $this->get('/admin/radio-channels')->assertRedirect(route('login'));
    }

    public function test_admin_can_log_in_with_valid_credentials(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'identity' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }

    public function test_a_signed_in_admin_visiting_login_is_sent_to_the_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_an_inactive_admin_cannot_log_in(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'identity' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('identity');
        $this->assertGuest('admin');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'identity' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('identity');
        $this->assertGuest('admin');
    }

    public function test_admin_can_log_out(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest('admin');
    }

    public function test_old_admin_login_url_redirects_to_the_unified_login(): void
    {
        $this->get('/admin/login')->assertRedirect('/login');
    }
}
