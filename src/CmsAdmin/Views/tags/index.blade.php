@extends('cms-admin::layout')

@section('title', 'Tags')

@section('content')
    @php
        // The categories the tags are filed under, once each (what the Category filter offers).
        $filterCategories = $tags->pluck('category_id')->unique()->map(fn ($id) => $categoryNames[$id] ?? null)->filter()->sort()->values();
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Tags') }}</h1>
            <p>{{ __('The nested "By Tag" tree used to filter and describe content — genres, topics, moods.') }}</p>
        </div>
        <a href="{{ route('admin.tags.create') }}" class="primary-button">{{ __('New tag') }}</a>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by Category and Level (a top-level tag or one nested under another), on top of the search box. The strip and its script are shared with the other lists. --}}
        @unless($tags->isEmpty())
            <div class="table-filters" id="tag-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Category') }}</span>
                    <span class="filter-control"><i class="fa fa-folder-o" aria-hidden="true"></i>
                        <select data-filter="category">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach($filterCategories as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Level') }}</span>
                    <span class="filter-control"><i class="fa fa-sitemap" aria-hidden="true"></i>
                        <select data-filter="level">
                            <option value="">{{ __('All levels') }}</option>
                            <option value="top">{{ __('Top level') }}</option>
                            <option value="sub">{{ __('Nested tags') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="tag-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tags as $tag)
                    <tr data-category="{{ $categoryNames[$tag->category_id] ?? '' }}" data-level="{{ $tag->depth > 0 ? 'sub' : 'top' }}">
                        {{-- Shown as an indented tree; a click on the heading sorts by the plain name (data-order), and a reload brings the tree back. --}}
                        <td style="padding-left:{{ 18 + $tag->depth * 22 }}px" data-order="{{ $tag->name }}">
                            @if($tag->depth > 0)<span class="muted">— </span>@endif<b>{{ $tag->name }}</b>
                        </td>
                        <td class="muted" data-label="{{ __('Category') }}">{{ $categoryNames[$tag->category_id] ?? '—' }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.tags.edit', $tag->id) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.tags.destroy', $tag->id) }}" onsubmit="return confirm(@js(__('Delete this tag? Any child tags will be deleted too.')))">
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
    @include('cms-admin::partials.filtered-table', ['tableId' => 'tag-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #tag-table td b { font-size: 15px; }
        /* The tree is long and narrow: no sideways scroll for it on wide screens. */
        #tag-table { min-width: 640px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#tag-table').DataTable({
                order: [],                                   // keep the tree order until a header is clicked
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search tags…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No tags yet.')),
                    zeroRecords: @js(__('No matching tags.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'tag-filters', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
