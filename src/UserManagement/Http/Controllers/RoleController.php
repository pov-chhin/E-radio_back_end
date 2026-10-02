<?php

namespace Src\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;

class RoleController extends Controller
{
    /** Resource slug => the label its row carries in the permissions matrix, in display order. */
    private const RESOURCE_LABELS = [
        'radio-channels' => 'Radio Channels',
        'programs' => 'Podcasts',
        'media-content' => 'Media Content',
        'categories' => 'Categories',
        'tags' => 'Tags',
        'artists' => 'Artists',
        'users' => 'Admin Users',
        'customers' => 'Customers',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
    ];

    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->latest()->paginate(15);

        return view('user-management::roles.index', compact('roles'));
    }

    public function create(): View
    {
        [$permissionMatrix, $actions, $otherPermissions] = $this->permissionMatrix();

        return view('user-management::roles.create', compact('permissionMatrix', 'actions', 'otherPermissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create($data);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        [$permissionMatrix, $actions, $otherPermissions] = $this->permissionMatrix();
        $role->loadMissing('permissions');

        return view('user-management::roles.edit', compact('role', 'permissionMatrix', 'actions', 'otherPermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role);

        $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /**
     * Groups permissions into a resource × action matrix for the form's checkbox table: each row is
     * a resource (Podcasts, Categories, …), each column a View/Create/Edit/Delete cell — blank where
     * that resource has no route for the action (e.g. Permissions can't be edited, only made or removed).
     * Anything that doesn't fit the `{action}-{resource}` slug shape (view-dashboard, manage-settings,
     * or a permission someone made by hand) falls through to a flat list instead.
     *
     * @return array{0: list<array{label: string, cells: array<string, ?Permission>}>, 1: list<string>, 2: \Illuminate\Support\Collection<int, Permission>}
     */
    private function permissionMatrix(): array
    {
        $permissions = Permission::orderBy('slug')->get();
        $actions = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];

        $matchedIds = [];
        $rows = [];

        foreach (self::RESOURCE_LABELS as $resource => $label) {
            $cells = [];
            foreach ($actions as $actionSlug => $actionLabel) {
                $permission = $permissions->firstWhere('slug', "{$actionSlug}-{$resource}");
                $cells[$actionLabel] = $permission;
                if ($permission) {
                    $matchedIds[] = $permission->id;
                }
            }
            $rows[] = ['label' => $label, 'cells' => $cells];
        }

        $otherPermissions = $permissions->reject(fn (Permission $permission) => in_array($permission->id, $matchedIds, true))->values();

        return [$rows, array_values($actions), $otherPermissions];
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'slug')->ignore($role)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
