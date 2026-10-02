@extends('cms-admin::layout')

@section('title', 'Permissions')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Access control</p>
            <h1>Permissions</h1>
            <p>The individual capabilities you can group into roles.</p>
        </div>
        <a href="{{ route('admin.permissions.create') }}" class="primary-button">New permission</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr>
                        <td><b>{{ $permission->name }}</b></td>
                        <td class="muted">{{ $permission->slug }}</td>
                        <td>
                            <div class="table-actions">
                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" onsubmit="return confirm('Delete this permission?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="3">No permissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $permissions->links() }}</div>
@endsection
