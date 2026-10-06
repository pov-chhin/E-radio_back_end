<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\CmsAdmin\Models\User;

/**
 * Token auth (Sanctum personal access tokens) for the e-Radio mobile app.
 * Only Src\CmsAdmin\Models\User (the "customer" guard) can authenticate here —
 * admin CMS accounts are a separate table scope and never get app tokens.
 */
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('e-radio-app')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('e-radio-app')->plainTextToken,
        ]);
    }

    /**
     * "Continue with Google". The app signs the person in with Google on the
     * device and sends the ID token Google issued; it is checked with Google
     * here, then the matching customer is found — by Google account, else by
     * the (Google-verified) email, which links an existing account — or created,
     * and the same app token as `login` comes back.
     */
    public function google(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $clientIds = (array) config('services.google.client_ids');
        if ($clientIds === []) {
            abort(503, 'Google sign-in is not available right now.');
        }

        $claims = $this->verifiedGoogleClaims($data['id_token'], $clientIds);

        $created = false;
        $user = User::where('google_id', $claims['sub'])->first();

        if (! $user) {
            $user = User::where('email', $claims['email'])->first();

            if ($user) {
                $user->update([
                    'google_id' => $claims['sub'],
                    // A picture they uploaded themselves stays.
                    'avatar_url' => $user->getRawOriginal('avatar_url') ?: ($claims['picture'] ?? null),
                ]);
            } else {
                // Admin accounts share the table (and its unique emails) but are
                // invisible to the customer scope above.
                if (User::withoutGlobalScopes()->where('email', $claims['email'])->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['This email address cannot be used to sign in here.'],
                    ]);
                }

                $user = User::create([
                    'first_name' => $claims['given_name'] ?? $claims['name'] ?? Str::before($claims['email'], '@'),
                    'last_name' => $claims['family_name'] ?? '',
                    'email' => $claims['email'],
                    'google_id' => $claims['sub'],
                    'avatar_url' => $claims['picture'] ?? null,
                ]);
                $created = true;
            }
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('e-radio-app')->plainTextToken,
        ], $created ? 201 : 200);
    }

    /**
     * Google's own tokeninfo endpoint checks the signature and expiry; what is
     * left for us is that the token was issued for this app, by Google, for an
     * email Google has verified.
     *
     * @param  list<string>  $clientIds
     * @return array<string, mixed>
     */
    private function verifiedGoogleClaims(string $idToken, array $clientIds): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (ConnectionException) {
            abort(503, 'Google could not be reached. Please try again.');
        }

        $claims = $response->successful() ? $response->json() : null;

        $valid = is_array($claims)
            && in_array($claims['aud'] ?? null, $clientIds, true)
            && in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && (int) ($claims['exp'] ?? 0) > time()
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($claims['sub'])
            && ! empty($claims['email']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'id_token' => ['Google sign-in could not be verified.'],
            ]);
        }

        return $claims;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return new UserResource($user);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $old = $user->getRawOriginal('avatar_url');
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar_url' => $path]);

        // Only files we stored are ours to delete — avatar_url can also hold an
        // external (e.g. Google) URL.
        if ($old && ! str_starts_with($old, 'http')) {
            Storage::disk('public')->delete($old);
        }

        return new UserResource($user);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->noContent();
    }
}
