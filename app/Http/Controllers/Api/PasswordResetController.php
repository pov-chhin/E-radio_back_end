<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\CmsAdmin\Models\User;
use Src\CmsAdmin\Notifications\PasswordResetCode;

/**
 * Forgotten password, for the mobile app, in three steps:
 *
 *   1. sendCode   — email a 6-digit code (valid for CODE_TTL_SECONDS),
 *   2. verifyCode — trade a correct code for a short-lived reset token,
 *   3. reset      — set the new password with that token.
 *
 * All state lives in the cache (codes and tokens are short-lived and
 * one-time), so this needs no migration. Nothing here reveals whether an
 * email address has an account: step 1 answers the same either way, and a
 * wrong or missing code in step 2 is one and the same error.
 */
class PasswordResetController extends Controller
{
    /** How long an emailed code works — the app counts this down ("Code expires in 1:59"). */
    public const CODE_TTL_SECONDS = 120;

    /** Minimum gap between two code requests for the same email. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Wrong guesses allowed before the code is thrown away. */
    public const MAX_CODE_ATTEMPTS = 5;

    /** How long a verified code's reset token can be used to set the password. */
    public const RESET_TOKEN_TTL_SECONDS = 600;

    public function sendCode(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $key = $this->cacheKey($email);

        // Counted whether or not an account exists, so a 429 can't be used to
        // tell registered emails from unregistered ones — and so one address
        // can't be mail-bombed from many devices.
        if (! Cache::add("password-reset:cooldown:{$key}", true, self::RESEND_COOLDOWN_SECONDS)) {
            return response()->json(['message' => 'Please wait a moment before asking for another code.'], 429);
        }

        $user = $this->findUser($email);

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Cache::put("password-reset:code:{$key}", [
                'hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS)->getTimestamp(),
            ], self::CODE_TTL_SECONDS);
            // A fresh code supersedes any reset token left from an earlier one.
            Cache::forget("password-reset:token:{$key}");

            $user->notify(new PasswordResetCode($code, intdiv(self::CODE_TTL_SECONDS, 60)));
        }

        return response()->json([
            'message' => "If an account exists for that email, we've sent a 6-digit code.",
            'expires_in' => self::CODE_TTL_SECONDS,
        ]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);
        $key = $this->cacheKey($data['email']);
        $codeKey = "password-reset:code:{$key}";

        $entry = Cache::get($codeKey);

        if (! $entry || $entry['expires_at'] <= now()->getTimestamp()) {
            Cache::forget($codeKey);
            $this->fail('code', 'That code is incorrect or has expired.');
        }

        if (! Hash::check($data['code'], $entry['hash'])) {
            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                Cache::forget($codeKey);
            } else {
                // Keep the original expiry — a wrong guess mustn't buy more time.
                Cache::put($codeKey, $entry, max(1, $entry['expires_at'] - now()->getTimestamp()));
            }

            $this->fail('code', 'That code is incorrect or has expired.');
        }

        // One use only.
        Cache::forget($codeKey);

        $token = Str::random(64);
        Cache::put("password-reset:token:{$key}", hash('sha256', $token), self::RESET_TOKEN_TTL_SECONDS);

        return response()->json([
            'reset_token' => $token,
            'expires_in' => self::RESET_TOKEN_TTL_SECONDS,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => [
                'required', 'string', 'min:8',
                'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'The password needs an uppercase letter, a number and a special character.',
        ]);
        $key = $this->cacheKey($data['email']);
        $tokenKey = "password-reset:token:{$key}";

        $stored = Cache::get($tokenKey);
        $user = $this->findUser($data['email']);

        if (! $stored || ! $user || ! hash_equals($stored, hash('sha256', $data['reset_token']))) {
            $this->fail('reset_token', 'This reset has expired. Please start again.');
        }

        $user->update(['password' => Hash::make($data['password'])]);
        // Anyone holding the old password (or a session made with it) is out.
        $user->tokens()->delete();
        Cache::forget($tokenKey);

        return response()->json(['message' => 'Your password has been reset.']);
    }

    private function cacheKey(string $email): string
    {
        return sha1($this->normalizeEmail($email));
    }

    /**
     * Lower-cased, and — for Gmail only — without dots in the name part, since
     * Gmail delivers `chhin.pov@gmail.com` and `chhinpov@gmail.com` to the same
     * inbox. Used for the account lookup and the cache keys, so a dotted
     * spelling reaches the same account, cooldown and code.
     */
    private function normalizeEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            return str_replace('.', '', $name).'@gmail.com';
        }

        return $email;
    }

    private function findUser(string $email): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user || ! str_ends_with($this->normalizeEmail($email), '@gmail.com')) {
            return $user;
        }

        // Gmail address spelled differently from how it was registered.
        $wanted = $this->normalizeEmail($email);

        return User::where('email', 'like', '%@gmail.com')
            ->orWhere('email', 'like', '%@googlemail.com')
            ->get()
            ->first(fn (User $u) => $this->normalizeEmail($u->email) === $wanted);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
