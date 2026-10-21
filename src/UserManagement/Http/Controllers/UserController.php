<?php

namespace Src\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Notification\Notifications\AdminUserRegistered;
use Src\Setting\Models\LanguageSetting;
use Src\User\User;
use Src\UserManagement\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        // Every admin user: the page's DataTable searches, sorts, filters and pages them in the browser.
        $users = User::with('roles')->latest()->get();

        return view('user-management::users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('user-management::users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            // Left empty, the account follows the site default from Settings → Language.
            'locale' => ['nullable', Rule::in(array_keys(LanguageSetting::LOCALES))],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? null,
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        $recipients = User::whereHas('roles.permissions', fn ($query) => $query->where('slug', 'view-users'))
            ->where('id', '!=', $request->user('admin')->id)
            ->get();
        Notification::send($recipients, new AdminUserRegistered($user->name, $user->email));

        return redirect()->route('admin.users.index')->with('status', __('User registered.'));
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();

        return view('user-management::users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Left empty, the current password stays.
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['nullable', Rule::in(array_keys(LanguageSetting::LOCALES))],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'] ?? null,
            ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        // As with deleting and deactivating, an admin can't lock themselves out: their own roles stay as they are.
        if ($user->id !== $request->user('admin')->id) {
            $user->roles()->sync($data['roles'] ?? []);
        }

        return redirect()->route('admin.users.index')->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user('admin')->id) {
            return back()->with('error', __("You can't delete your own account."));
        }

        $user->delete();

        return back()->with('status', __('User deleted.'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user('admin')->id) {
            return back()->with('error', __("You can't deactivate your own account."));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active ? __('User activated.') : __('User deactivated.'));
    }
}
