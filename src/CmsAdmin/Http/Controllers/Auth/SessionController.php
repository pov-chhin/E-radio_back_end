<?php

namespace Src\CmsAdmin\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('cms-admin::auth.login');
    }

    /**
     * One login form for both account systems: admins (by email) and app
     * customers (by username). Both are rows in the same `users` table,
     * scoped by type. Tries the admin guard first, then falls back to customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $remember = $request->boolean('remember');

        if (Auth::guard('admin')->attempt(['email' => $credentials['identity'], 'password' => $credentials['password'], 'is_active' => true], $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        if (Auth::guard('customer')->attempt(['username' => $credentials['identity'], 'password' => $credentials['password']], $remember)) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors(['identity' => __('These credentials do not match our records.')])->onlyInput('identity');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        } elseif (Auth::guard('customer')->check()) {
            Auth::guard('customer')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
