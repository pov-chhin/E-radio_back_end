@extends('cms-admin::layout')

@section('title', 'Categories')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Categories') }}</h1>
            <p>{{ __('The top-level sections listeners browse by — Podcast, News, Song, and so on.') }}</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="primary-button">{{ __('New category') }}</a>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by whether a category has Podcasts and whether it has Media content, on top of the search box. The strip and its script are shared with the other lists. --}}
        @unless($categories->isEmpty())
            <div class="table-filters" id="category-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Podcasts') }}</span>
                    <span class="filter-control"><i class="fa fa-podcast" aria-hidden="true"></i>
                        <select data-filter="podcasts">
                            <option value="">{{ __('All') }}</option>
                            <option value="with">{{ __('With podcasts') }}</option>
                            <option value="none">{{ __('No podcasts') }}</option>
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Media content') }}</span>
                    <span class="filter-control"><i class="fa fa-music" aria-hidden="true"></i>
                        <select data-filter="content">
                            <option value="">{{ __('All') }}</option>
                            <option value="with">{{ __('With media content') }}</option>
                            <option value="none">{{ __('No media content') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="category-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Slug') }}</th>
                    <th>{{ __('Podcasts') }}</th>
                    <th>{{ __('Media content') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                    <tr data-podcasts="{{ $category->programs_count > 0 ? 'with' : 'none' }}" data-content="{{ $category->media_contents_count > 0 ? 'with' : 'none' }}">
                        <td><b>{{ $category->name }}</b></td>
                        <td class="muted" data-label="{{ __('Slug') }}">{{ $category->slug }}</td>
                        <td data-label="{{ __('Podcasts') }}" data-order="{{ (int) $category->programs_count }}">{{ $category->programs_count }}</td>
                        <td data-label="{{ __('Media content') }}" data-order="{{ (int) $category->media_contents_count }}">{{ $category->media_contents_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm(@js(__('Delete this category? Its podcasts and media content will be deleted too.')))">
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
    @include('cms-admin::partials.filtered-table', ['tableId' => 'category-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #category-table td b { font-size: 15px; }
        /* Four short columns: they need less room than the wider lists before the table scrolls sideways. */
        #category-table { min-width: 680px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#category-table').DataTable({
                order: [],                                   // keep the order set for the categories until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search categories…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No categories yet.')),
                    zeroRecords: @js(__('No matching categories.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'category-filters', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
