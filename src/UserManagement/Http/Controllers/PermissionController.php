<?php

namespace Src\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\UserManagement\Models\Permission;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::orderBy('name')->paginate(15);

        return view('user-management::permissions.index', compact('permissions'));
    }

    public function create(): View
    {
        return view('user-management::permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:permissions,slug'],
        ]);

        Permission::create($data);

        return redirect()->route('admin.permissions.index')->with('status', __('Permission created.'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('status', __('Permission deleted.'));
    }
}
