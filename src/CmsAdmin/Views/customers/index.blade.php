@extends('cms-admin::layout')

@section('title', 'Customers')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('Customers') }}</h1>
            <p>{{ __('Accounts registered through the app.') }}</p>
        </div>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by whether a customer has bookmarks and whether they have a username, on top of the search box. The strip and its script are shared with the other lists. --}}
        @unless($customers->isEmpty())
            <div class="table-filters" id="customer-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Bookmarks') }}</span>
                    <span class="filter-control"><i class="fa fa-bookmark-o" aria-hidden="true"></i>
                        <select data-filter="bookmarks">
                            <option value="">{{ __('All') }}</option>
                            <option value="with">{{ __('With bookmarks') }}</option>
                            <option value="none">{{ __('No bookmarks') }}</option>
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Username') }}</span>
                    <span class="filter-control"><i class="fa fa-at" aria-hidden="true"></i>
                        <select data-filter="username">
                            <option value="">{{ __('All') }}</option>
                            <option value="with">{{ __('With username') }}</option>
                            <option value="none">{{ __('No username') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="customer-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Username') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Joined') }}</th>
                    <th class="right">{{ __('Bookmarks') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $customer)
                    <tr data-bookmarks="{{ $customer->bookmarks_count > 0 ? 'with' : 'none' }}" data-username="{{ filled($customer->username) ? 'with' : 'none' }}">
                        <td data-order="{{ $customer->name ?: $customer->email }}">
                            <div class="cust-cell">
                                {{-- The initial sits underneath, so a customer without a photo (or one that fails to load) still gets an avatar. --}}
                                <span class="cust-avatar">{{ mb_strtoupper(mb_substr(trim($customer->name ?: $customer->email), 0, 1)) ?: '?' }}@if($customer->avatar_url)<img src="{{ $customer->avatar_url }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
                                <b>{{ $customer->name ?: '—' }}</b>
                            </div>
                        </td>
                        <td class="muted" data-label="{{ __('Username') }}">{{ $customer->username ?? '—' }}</td>
                        <td class="muted" data-label="{{ __('Email') }}">{{ $customer->email }}</td>
                        <td class="muted" data-label="{{ __('Joined') }}" data-order="{{ $customer->created_at->timestamp }}">{{ $customer->created_at->translatedFormat('M j, Y') }}</td>
                        <td class="right" data-label="{{ __('Bookmarks') }}" data-order="{{ (int) $customer->bookmarks_count }}">{{ $customer->bookmarks_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- DataTables: search, click-to-sort headers and paging over the rows above — an empty list too, which says so through `emptyTable`.
         The strip's styles and script are shared with the other filtered lists. --}}
    @include('cms-admin::partials.filtered-table', ['tableId' => 'customer-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #customer-table .cust-cell b { font-size: 15px; }
        /* Five short columns: they need less room than the wider lists before the table scrolls sideways. */
        #customer-table { min-width: 760px; }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#customer-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search customers…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No customers yet.')),
                    zeroRecords: @js(__('No matching customers.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'customer-filters', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
