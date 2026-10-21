@extends('cms-admin::layout')

@section('title', 'Artists')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Artists') }}</h1>
            <p>{{ __('Artist profiles — bio, photo, socials, and verification status.') }}</p>
        </div>
        <a href="{{ route('admin.artists.create') }}" class="primary-button"><i class="fa fa-plus"></i> {{ __('Add Artist') }}</a>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by Active and Region, on top of the search box. The strip and its script are shared with the other lists. --}}
        @unless($artists->isEmpty())
            <div class="table-filters" id="artist-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Active') }}</span>
                    <span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"></i>
                        <select data-filter="active">
                            <option value="">{{ __('All') }}</option>
                            <option value="active">{{ __('Active') }}</option>
                            <option value="inactive">{{ __('Inactive') }}</option>
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Region') }}</span>
                    <span class="filter-control"><i class="fa fa-globe" aria-hidden="true"></i>
                        <select data-filter="region">
                            <option value="">{{ __('All regions') }}</option>
                            <option value="international">{{ __('International') }}</option>
                            <option value="local">{{ __('Local') }}</option>
                            <option value="none">{{ __('Not set') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="artist-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th>{{ __('Region') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($artists as $artist)
                    <tr data-active="{{ $artist->verified ? 'active' : 'inactive' }}" data-region="{{ $artist->region ?: 'none' }}">
                        {{-- Sorts by the name shown (the stage name where there is one), not by the initials of the avatar. --}}
                        <td data-order="{{ $artist->stage_name ?: $artist->name }}">
                            <div class="table-name-cell">
                                @if($artist->photo)
                                    <img class="table-avatar" src="{{ $artist->photo_url }}" alt="">
                                @else
                                    <span class="table-avatar-fallback">{{ strtoupper(substr($artist->stage_name ?: $artist->name, 0, 2)) }}</span>
                                @endif
                                <div>
                                    <b>{{ $artist->stage_name ?: $artist->name }}</b>
                                    @if($artist->stage_name)<br><small class="muted">{{ $artist->name }}</small>@endif
                                </div>
                            </div>
                        </td>
                        <td data-label="{{ __('Active') }}">
                            @if($artist->verified)
                                <span class="badge green"><i class="fa fa-check"></i> {{ __('Active') }}</span>
                            @else
                                <span class="badge slate">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('Region') }}">
                            @if($artist->region)
                                <span class="badge slate">{{ __(ucfirst($artist->region)) }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.artists.edit', $artist) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.artists.destroy', $artist) }}" onsubmit="return confirm(@js(__('Delete this artist?')))">
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
    @include('cms-admin::partials.filtered-table', ['tableId' => 'artist-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #artist-table .table-name-cell b { font-size: 15px; }
        #artist-table td small { font-size: 11px; }
        /* Four columns: they need less room than the wider lists before the table scrolls sideways. */
        #artist-table { min-width: 720px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#artist-table').DataTable({
                order: [],                                   // keep the A–Z order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search artists…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No artists yet.')),
                    zeroRecords: @js(__('No matching artists.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'artist-filters', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
