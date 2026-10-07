@extends('cms-admin::layout')

@section('title', 'Admin Users')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Team') }}</p>
            <h1>{{ __('Admin Users') }}</h1>
            <p>{{ __('Accounts that can sign into the CMS admin panel.') }}</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="primary-button">{{ __('Register user') }}</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Roles') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><b>{{ $user->name }}</b></td>
                        <td class="muted">{{ $user->email }}</td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge slate">{{ __($role->name) }}</span>
                            @empty
                                <span class="muted">{{ __('None') }}</span>
                            @endforelse
                        </td>
                        <td>
                            @if($user->is_active)
                                <span class="badge green">{{ __('Active') }}</span>
                            @else
                                <span class="badge slate">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.users.edit', $user) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.users.toggleActive', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="secondary-button compact">
                                        {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm(@js(__('Delete this user?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">{{ __('No admin users yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
