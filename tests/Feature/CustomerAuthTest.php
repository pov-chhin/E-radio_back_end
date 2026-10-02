<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Src\CmsAdmin\Models\User;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create account');
    }

    public function test_a_user_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('users', ['type' => 'customer', 'username' => 'janedoe', 'email' => 'jane@example.com']);

        $user = User::where('username', 'janedoe')->firstOrFail();
        $this->assertSame('Jane Doe', $user->name);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->post('/register', [
            'first_name' => 'Jane',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'nope',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('customer');
    }

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        $this->createUser();

        $this->post('/login', [
            'identity' => 'janedoe',
            'password' => 'password123',
        ])->assertRedirect('/');

        $this->assertAuthenticated('customer');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createUser();

        $this->post('/login', [
            'identity' => 'janedoe',
            'password' => 'wrong',
        ])->assertSessionHasErrors('identity');

        $this->assertGuest('customer');
    }

    public function test_a_logged_in_user_can_log_out(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'customer')
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest('customer');
    }

    public function test_an_authenticated_customer_is_redirected_away_from_login(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'customer')
            ->get('/login')
            ->assertRedirect();
    }
}
