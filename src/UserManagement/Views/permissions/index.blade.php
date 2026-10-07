@extends('cms-admin::layout')

@section('title', 'Permissions')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('Permissions') }}</h1>
            <p>{{ __('The individual capabilities you can group into roles.') }}</p>
        </div>
        <a href="{{ route('admin.permissions.create') }}" class="primary-button">{{ __('New permission') }}</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Slug') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr>
                        <td><b>{{ __($permission->name) }}</b></td>
                        <td class="muted">{{ $permission->slug }}</td>
                        <td>
                            <div class="table-actions">
                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" onsubmit="return confirm(@js(__('Delete this permission?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="3">{{ __('No permissions yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $permissions->links() }}</div>
@endsection
