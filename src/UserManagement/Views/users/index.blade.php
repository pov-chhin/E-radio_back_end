@extends('cms-admin::layout')

@section('title', 'Admin Users')

@section('content')
    @php
        // The roles the users have, once each (what the Role filter offers); a user may have several, or none.
        $filterRoles = $users->flatMap(fn ($user) => $user->roles->pluck('name'))->unique()->sort()->values();
        $hasUnroled = $users->contains(fn ($user) => $user->roles->isEmpty());
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('Admin Users') }}</h1>
            <p>{{ __('Accounts that can sign into the CMS admin panel.') }}</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="primary-button">{{ __('Register user') }}</a>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by Role and Status, on top of the search box. The strip and its script are shared with the other lists. --}}
        @unless($users->isEmpty())
            <div class="table-filters" id="user-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Role') }}</span>
                    <span class="filter-control"><i class="fa fa-id-badge" aria-hidden="true"></i>
                        <select data-filter="role">
                            <option value="">{{ __('All roles') }}</option>
                            @foreach($filterRoles as $name)
                                <option value="{{ $name }}">{{ __($name) }}</option>
                            @endforeach
                            @if($hasUnroled)
                                <option value="none">{{ __('No role') }}</option>
                            @endif
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Status') }}</span>
                    <span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"></i>
                        <select data-filter="status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="user-table">
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
                @foreach($users as $user)
                    {{-- A user may have several roles: the row lists them joined by | for the Role filter to match any of them. --}}
                    <tr data-role="{{ $user->roles->isEmpty() ? 'none' : $user->roles->pluck('name')->implode('|') }}" data-status="{{ $user->is_active ? 'active' : 'inactive' }}">
                        <td><b>{{ $user->name }}</b></td>
                        <td class="muted" data-label="{{ __('Email') }}">{{ $user->email }}</td>
                        <td data-label="{{ __('Roles') }}" data-order="{{ $user->roles->pluck('name')->sort()->implode(', ') }}">
                            @forelse($user->roles as $role)
                                <span class="badge slate">{{ __($role->name) }}</span>
                            @empty
                                <span class="muted">{{ __('None') }}</span>
                            @endforelse
                        </td>
                        <td data-label="{{ __('Status') }}">
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
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- DataTables: search, click-to-sort headers and paging over the rows above — an empty list too, which says so through `emptyTable`.
         The strip's styles and script are shared with the other filtered lists. --}}
    @include('cms-admin::partials.filtered-table', ['tableId' => 'user-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #user-table td b { font-size: 15px; }
        /* Five short columns: they need less room than the wider lists before the table scrolls sideways. */
        #user-table { min-width: 820px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#user-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search users…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No admin users yet.')),
                    zeroRecords: @js(__('No matching users.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'user-filters', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
