<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Src\CmsAdmin\Models\User;
use Tests\TestCase;

class ApiGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'web-client.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_ids' => [self::CLIENT_ID]]);
    }

    /** What Google's tokeninfo answers for a good ID token. */
    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => '110169484474386276334',
            'email' => 'sophea@gmail.com',
            // tokeninfo sends these as strings.
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
            'name' => 'Sophea Chan',
            'given_name' => 'Sophea',
            'family_name' => 'Chan',
            'picture' => 'https://lh3.googleusercontent.com/a/sophea',
        ], $overrides);
    }

    private function googleSays(array $claims, int $status = 200): void
    {
        Http::fake(['oauth2.googleapis.com/tokeninfo*' => Http::response($claims, $status)]);
    }

    private function signIn(string $token = 'google-id-token')
    {
        return $this->postJson('/api/auth/google', ['id_token' => $token]);
    }

    public function test_a_new_google_account_gets_a_customer_and_a_token(): void
    {
        $this->googleSays($this->claims());

        $this->signIn()
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'email'], 'token'])
            ->assertJsonPath('user.email', 'sophea@gmail.com');

        $user = User::where('email', 'sophea@gmail.com')->firstOrFail();
        $this->assertSame('customer', $user->type);
        $this->assertSame('Sophea Chan', $user->name);
        $this->assertSame('110169484474386276334', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/a/sophea', $user->avatar_url);
        $this->assertNull($user->getRawOriginal('password'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_the_id_token_is_what_gets_checked_with_google(): void
    {
        $this->googleSays($this->claims());

        $this->signIn('the-token-from-the-app')->assertCreated();

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://oauth2.googleapis.com/tokeninfo')
            && $request['id_token'] === 'the-token-from-the-app');
    }

    public function test_a_returning_google_account_signs_in_to_the_same_customer(): void
    {
        $this->googleSays($this->claims());
        $this->signIn()->assertCreated();

        // Their Google email has since changed; the Google account is what counts.
        $this->googleSays($this->claims(['email' => 'new-address@gmail.com']));
        $this->signIn()->assertOk();

        $this->assertSame(1, User::count());
    }

    public function test_an_existing_account_with_that_email_is_linked(): void
    {
        $existing = User::create([
            'first_name' => 'Sophea',
            'last_name' => 'Chan',
            'email' => 'sophea@gmail.com',
            'password' => Hash::make('password123'),
        ]);
        $this->googleSays($this->claims());

        $this->signIn()->assertOk()->assertJsonPath('user.id', (string) $existing->id);

        $existing->refresh();
        $this->assertSame('110169484474386276334', $existing->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/a/sophea', $existing->avatar_url);
        $this->assertSame(1, User::count());
        // Their password still works.
        $this->postJson('/api/login', ['email' => 'sophea@gmail.com', 'password' => 'password123'])->assertOk();
    }

    public function test_linking_keeps_a_picture_the_person_uploaded(): void
    {
        User::create([
            'first_name' => 'Sophea',
            'last_name' => 'Chan',
            'email' => 'sophea@gmail.com',
            'password' => Hash::make('password123'),
            'avatar_url' => 'avatars/mine.png',
        ]);
        $this->googleSays($this->claims());

        $this->signIn()->assertOk();

        $this->assertSame('avatars/mine.png', User::first()->getRawOriginal('avatar_url'));
    }

    public function test_a_google_only_account_cannot_sign_in_with_a_password(): void
    {
        $this->googleSays($this->claims());
        $this->signIn()->assertCreated();

        $this->postJson('/api/login', ['email' => 'sophea@gmail.com', 'password' => ''])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'sophea@gmail.com', 'password' => 'anything'])->assertStatus(422);
    }

    public function test_a_name_is_made_up_when_google_gives_none(): void
    {
        $this->googleSays($this->claims(['name' => null, 'given_name' => null, 'family_name' => null]));

        $this->signIn()->assertCreated();

        $this->assertSame('sophea', User::first()->first_name);
    }

    public function test_a_token_issued_for_another_app_is_refused(): void
    {
        $this->googleSays($this->claims(['aud' => 'someone-elses-client.apps.googleusercontent.com']));

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('id_token');

        $this->assertSame(0, User::count());
    }

    public function test_a_token_not_issued_by_google_is_refused(): void
    {
        $this->googleSays($this->claims(['iss' => 'https://evil.example.com']));

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('id_token');
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->googleSays($this->claims(['email_verified' => 'false']));

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('id_token');

        $this->assertSame(0, User::count());
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->googleSays($this->claims(['exp' => (string) (time() - 60)]));

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('id_token');
    }

    public function test_a_token_google_rejects_is_refused(): void
    {
        $this->googleSays(['error' => 'invalid_token'], 400);

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('id_token');

        $this->assertSame(0, User::count());
    }

    public function test_the_id_token_is_required(): void
    {
        Http::fake();

        $this->postJson('/api/auth/google', [])->assertStatus(422)->assertJsonValidationErrors('id_token');

        Http::assertNothingSent();
    }

    public function test_google_sign_in_is_off_without_a_client_id(): void
    {
        config(['services.google.client_ids' => []]);
        Http::fake();

        $this->signIn()->assertStatus(503);

        Http::assertNothingSent();
        $this->assertSame(0, User::count());
    }

    public function test_an_unreachable_google_is_a_try_again_not_a_crash(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->signIn()->assertStatus(503);

        $this->assertSame(0, User::count());
    }

    public function test_an_admin_account_with_that_email_is_not_taken_over(): void
    {
        $admin = User::withoutGlobalScopes()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'sophea@gmail.com',
            'password' => Hash::make('password123'),
        ]);
        $admin->forceFill(['type' => 'admin'])->save();
        $this->googleSays($this->claims());

        $this->signIn()->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertNull(User::withoutGlobalScopes()->find($admin->id)->google_id);
    }
}
