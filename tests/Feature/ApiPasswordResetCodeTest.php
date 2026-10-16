<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\CmsAdmin\Models\User;
use Src\CmsAdmin\Notifications\PasswordResetCode;
use Tests\TestCase;

/**
 * The mobile app's forgotten-password flow: emailed code → reset token → new
 * password. Codes are only ever stored hashed, so these tests read the code
 * from the notification that was "sent".
 */
class ApiPasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'NewPass1!';

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => Hash::make('OldPass1!'),
        ], $overrides));
    }

    /** Requests a code for $user and returns the code that was emailed. */
    private function requestCode(User $user): string
    {
        Notification::fake();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();

        $code = null;
        Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    /** Runs steps 1 and 2 and returns the reset token. */
    private function verifiedToken(User $user): string
    {
        $code = $this->requestCode($user);

        return $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertOk()
            ->json('reset_token');
    }

    // ---- step 1: send the code -------------------------------------------

    public function test_a_code_is_emailed_to_an_existing_account(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => 'jane@example.com'])
            ->assertOk()
            ->assertJsonPath('expires_in', PasswordResetController::CODE_TTL_SECONDS);

        Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $n) {
            return preg_match('/^\d{6}$/', $n->code) === 1;
        });
    }

    public function test_an_unknown_email_gets_the_same_answer_and_no_email(): void
    {
        $this->createUser();
        Notification::fake();

        $known = $this->postJson('/api/password/forgot', ['email' => 'jane@example.com']);
        $unknown = $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com']);

        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes(User::where('email', 'jane@example.com')->first(), PasswordResetCode::class, 1);
        Notification::assertCount(1);
    }

    public function test_the_email_must_be_valid(): void
    {
        $this->postJson('/api/password/forgot', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_asking_again_straight_away_is_refused(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertStatus(429);

        Notification::assertCount(1);
    }

    public function test_the_cooldown_applies_to_unknown_emails_too(): void
    {
        // Otherwise a 429 would reveal which emails are registered.
        $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])->assertOk();
        $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    public function test_a_new_code_can_be_requested_after_the_cooldown(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->travel(PasswordResetController::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentToTimes($user, PasswordResetCode::class, 2);
    }

    public function test_a_gmail_address_spelled_with_dots_reaches_the_same_account(): void
    {
        $user = $this->createUser(['email' => 'chhinpov2025@gmail.com', 'username' => 'chhinpov']);
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => 'Chhinpov.2025@gmail.com'])->assertOk();

        $code = null;
        Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $token = $this->postJson('/api/password/verify-code', ['email' => 'chhinpov.2025@gmail.com', 'code' => $code])
            ->assertOk()
            ->json('reset_token');

        $this->postJson('/api/password/reset', [
            'email' => 'chhinpov.2025@gmail.com',
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->fresh()->password));
    }

    public function test_dots_only_count_for_gmail_addresses(): void
    {
        $user = $this->createUser(['email' => 'jane@example.com']);
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => 'ja.ne@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    // ---- step 2: verify the code -----------------------------------------

    public function test_the_right_code_gives_a_reset_token(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);

        $response = $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code]);

        $response->assertOk()->assertJsonPath('expires_in', PasswordResetController::RESET_TOKEN_TTL_SECONDS);
        $this->assertNotEmpty($response->json('reset_token'));
    }

    public function test_a_code_only_works_once(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);

        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])->assertOk();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $wrong])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_the_code_is_thrown_away_after_too_many_wrong_guesses(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < PasswordResetController::MAX_CODE_ATTEMPTS; $i++) {
            $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $wrong])->assertUnprocessable();
        }

        // Even the correct code no longer works.
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_wrong_guess_does_not_extend_the_code(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->travel(PasswordResetController::CODE_TTL_SECONDS - 10)->seconds();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $wrong])->assertUnprocessable();
        $this->travel(11)->seconds();

        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->createUser();
        $code = $this->requestCode($user);

        $this->travel(PasswordResetController::CODE_TTL_SECONDS + 1)->seconds();

        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_code_for_someone_else_is_refused(): void
    {
        $jane = $this->createUser();
        $joe = $this->createUser(['username' => 'joe', 'email' => 'joe@example.com']);
        $code = $this->requestCode($jane);

        $this->postJson('/api/password/verify-code', ['email' => $joe->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $this->postJson('/api/password/verify-code', ['email' => 'jane@example.com', 'code' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    // ---- step 3: set the new password ------------------------------------

    public function test_the_password_is_reset_with_a_verified_token(): void
    {
        $user = $this->createUser();
        $token = $this->verifiedToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->fresh()->password));
        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::STRONG_PASSWORD])->assertOk();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'OldPass1!'])->assertUnprocessable();
    }

    public function test_resetting_signs_out_every_existing_session(): void
    {
        $user = $this->createUser();
        $user->createToken('e-radio-app');
        $token = $this->verifiedToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_reset_token_only_works_once(): void
    {
        $user = $this->createUser();
        $token = $this->verifiedToken($user);
        $payload = ['email' => $user->email, 'reset_token' => $token, 'password' => self::STRONG_PASSWORD];

        $this->postJson('/api/password/reset', $payload)->assertOk();
        $this->postJson('/api/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reset_token');
    }

    public function test_a_wrong_token_is_refused_and_the_password_is_unchanged(): void
    {
        $user = $this->createUser();
        $this->verifiedToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => 'not-the-token',
            'password' => self::STRONG_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('reset_token');

        $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));
    }

    public function test_a_token_cannot_reset_a_different_account(): void
    {
        $jane = $this->createUser();
        $joe = $this->createUser(['username' => 'joe', 'email' => 'joe@example.com']);
        $token = $this->verifiedToken($jane);

        $this->postJson('/api/password/reset', [
            'email' => $joe->email,
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('reset_token');

        $this->assertTrue(Hash::check('OldPass1!', $joe->fresh()->password));
    }

    public function test_the_reset_token_expires(): void
    {
        $user = $this->createUser();
        $token = $this->verifiedToken($user);

        $this->travel(PasswordResetController::RESET_TOKEN_TTL_SECONDS + 1)->seconds();

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    public function test_a_new_code_cancels_a_reset_token_from_an_earlier_one(): void
    {
        $user = $this->createUser();
        $oldToken = $this->verifiedToken($user);

        $this->travel(PasswordResetController::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->requestCode($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $oldToken,
            'password' => self::STRONG_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('reset_token');
    }

    #[DataProvider('weakPasswords')]
    public function test_a_password_missing_a_requirement_is_refused(string $password): void
    {
        $user = $this->createUser();
        $token = $this->verifiedToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));
    }

    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Ab1!'],
            'no uppercase' => ['newpass1!'],
            'no number' => ['NewPass!!'],
            'no special character' => ['NewPass12'],
        ];
    }

    public function test_each_step_has_its_own_rate_limit(): void
    {
        // Wrong guesses at the code must not use up the allowance for asking
        // for a new one, or for resetting.
        $user = $this->createUser();
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => '123456'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
    }

    public function test_verifying_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/password/verify-code', ['email' => 'jane@example.com', 'code' => '123456'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/password/verify-code', ['email' => 'jane@example.com', 'code' => '123456'])
            ->assertStatus(429);
    }

    public function test_the_email_shows_the_code_and_how_long_it_lasts(): void
    {
        $user = $this->createUser();

        $html = (string) (new PasswordResetCode('482913', 2))->toMail($user)->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('expires in 2 minutes', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('e-radio-ams-logo.png', $html);
        $this->assertStringNotContainsString('Regards,', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_the_flow_leaves_nothing_behind_in_the_cache(): void
    {
        $user = $this->createUser();
        $token = $this->verifiedToken($user);
        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => self::STRONG_PASSWORD,
        ])->assertOk();

        $key = sha1($user->email);
        $this->assertFalse(Cache::has("password-reset:code:{$key}"));
        $this->assertFalse(Cache::has("password-reset:token:{$key}"));
    }
}
