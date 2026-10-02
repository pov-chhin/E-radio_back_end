@extends('cms-admin::layout')

@section('title', 'Admin Users')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Team</p>
            <h1>Admin Users</h1>
            <p>Accounts that can sign into the CMS admin panel.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="primary-button">Register user</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><b>{{ $user->name }}</b></td>
                        <td class="muted">{{ $user->email }}</td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge slate">{{ $role->name }}</span>
                            @empty
                                <span class="muted">None</span>
                            @endforelse
                        </td>
                        <td>
                            @if($user->is_active)
                                <span class="badge green">Active</span>
                            @else
                                <span class="badge slate">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <form method="POST" action="{{ route('admin.users.toggleActive', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="secondary-button compact">
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">No admin users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
