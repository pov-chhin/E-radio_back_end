<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Src\Catalog\Services\ImageVariantProcessor;
use Src\Catalog\Services\UnreadableImageException;
use Src\Setting\Models\LanguageSetting;
use Src\User\User;

/** The signed-in admin's own account: any admin can open it, so there is no permission to hold. */
class ProfileController extends Controller
{
    /** The photo is cropped to this square and stored as WebP, like the artist photos. */
    private const PHOTO_SIZE = 300;

    private const PHOTO_DIRECTORY = 'avatars';

    public function __construct(private readonly ImageVariantProcessor $imageProcessor) {}

    public function show(Request $request): View
    {
        return view('cms-admin::profile.show', ['user' => $request->user('admin')->load('roles')]);
    }

    public function edit(Request $request): View
    {
        return view('cms-admin::profile.edit', ['user' => $request->user('admin')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-().\s]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            // Left empty, the account follows the site default from Settings → Language.
            'locale' => ['nullable', Rule::in(array_keys(LanguageSetting::LOCALES))],
            // Changing the password means proving you know the current one; leaving both empty keeps it.
            'current_password' => ['nullable', 'required_with:password', 'current_password:admin'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Only these fields: roles and active status are managed on the Admin Users page, not here.
        $user->update([
            'name' => $data['name'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'address' => $data['address'] ?? null,
            'locale' => $data['locale'] ?? null,
            ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
            ...$this->photoChange($request, $user),
        ]);

        return redirect()->route('admin.profile.show')->with('status', __('Profile updated.'));
    }

    /**
     * What to change about the photo: a new upload becomes a square WebP and replaces the old file, "remove" clears
     * it, and otherwise there is nothing to change. Only an upload is taken — never an address to download from,
     * since every admin can reach this page.
     *
     * @return array{avatar_url?: ?string}
     */
    private function photoChange(Request $request, User $user): array
    {
        $current = $user->getRawOriginal('avatar_url');

        if ($request->hasFile('photo')) {
            try {
                $path = $this->imageProcessor->process($request->file('photo'), self::PHOTO_DIRECTORY, self::PHOTO_SIZE, self::PHOTO_SIZE);
            } catch (UnreadableImageException $e) {
                throw ValidationException::withMessages(['photo' => $e->getMessage()]);
            }

            $this->forgetPhoto($current);

            return ['avatar_url' => $path];
        }

        if ($request->boolean('remove_photo')) {
            $this->forgetPhoto($current);

            return ['avatar_url' => null];
        }

        return [];
    }

    /** Only a file we stored is ours to delete: an account can also carry an external (Google) photo address. */
    private function forgetPhoto(?string $stored): void
    {
        if (filled($stored) && ! str_starts_with($stored, 'http')) {
            Storage::disk(MediaStorage::LOCAL)->delete($stored);
        }
    }
}
