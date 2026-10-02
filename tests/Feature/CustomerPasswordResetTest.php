<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Src\CmsAdmin\Models\User;
use Src\CmsAdmin\Notifications\AccountRecovery;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot username or password');
    }

    public function test_it_sends_an_account_recovery_notification_containing_the_username(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->post('/forgot-password', ['email' => 'jane@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, AccountRecovery::class);
    }

    public function test_it_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect()->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_a_user_can_reset_their_password_with_a_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker('customers')->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'identity' => 'janedoe',
            'password' => 'new-password123',
        ])->assertRedirect('/');

        $this->assertAuthenticated('customer');
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $this->createUser();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'jane@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }
}
