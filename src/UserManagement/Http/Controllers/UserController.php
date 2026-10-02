<?php

namespace Src\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Src\Notification\Notifications\AdminUserRegistered;
use Src\User\User;
use Src\UserManagement\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->latest()->paginate(15);

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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        $recipients = User::whereHas('roles.permissions', fn ($query) => $query->where('slug', 'view-users'))
            ->where('id', '!=', $request->user('admin')->id)
            ->get();
        Notification::send($recipients, new AdminUserRegistered($user->name, $user->email));

        return redirect()->route('admin.users.index')->with('status', 'User registered.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user('admin')->id) {
            return back()->with('error', "You can't delete your own account.");
        }

        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user('admin')->id) {
            return back()->with('error', "You can't deactivate your own account.");
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }
}
