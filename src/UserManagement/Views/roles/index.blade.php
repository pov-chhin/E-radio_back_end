@extends('cms-admin::layout')

@section('title', 'Roles')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Access control</p>
            <h1>Roles</h1>
            <p>Group permissions into roles, then assign roles to admin users.</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="primary-button">New role</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Permissions</th>
                    <th>Users</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td><b>{{ $role->name }}</b></td>
                        <td class="muted">{{ $role->slug }}</td>
                        <td>{{ $role->permissions_count }}</td>
                        <td>{{ $role->users_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">No roles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $roles->links() }}</div>
@endsection
