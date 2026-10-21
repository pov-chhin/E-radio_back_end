@extends('cms-admin::layout')

@section('title', 'Podcasts')

@section('content')
    @php
        // The day each podcast was added, in the admin's own timezone (what the Added column shows and the From / To filters compare).
        $addedAt = fn ($program) => $program->created_at?->copy()->setTimezone(config('app.admin_timezone'));
        $filterCategories = $programs->map(fn ($program) => $program->type?->name)->filter()->unique()->sort()->values();
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Content') }}</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>{{ __('Podcasts') }}</h1>
                {{-- One tab per podcast type, taken from the tags under Podcast; each submits the GET form with its slug.
                     The tab bar is hidden for now (the Podcast Category filter below narrows the list); the tabs and their ?type= addresses still work. --}}
                <form method="GET" action="{{ route('admin.programs.index') }}" aria-label="{{ __('Filter by podcast category') }}" hidden>
                    <div class="tag-view-toggle">
                        <button type="submit" name="type" value="" style="padding:7px 14px;font-size:13px" @class(['active' => ! $activeType])>{{ __('All') }}</button>
                        @foreach($types as $type)
                            <button type="submit" name="type" value="{{ $type->slug }}" style="padding:7px 14px;font-size:13px" @class(['active' => $activeType?->is($type)])>{{ $type->name }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>{{ __('Shows and their episodes, as listeners see them in the app.') }}</p>
        </div>
        {{-- One straight link to the create page; the podcast type is picked on the form (an open type tab is already selected there). --}}
        <a href="{{ route('admin.programs.create', $activeType ? ['type' => $activeType->slug] : []) }}" class="primary-button">{{ __('New Podcast Program') }} <i class="fa fa-plus" aria-hidden="true"></i></a>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by Podcast Category and Status, on top of the search box (no date boxes: the shared script also takes from / to ones). The strip and its script are shared with the Content list. --}}
        @unless($programs->isEmpty())
            <div class="table-filters" id="program-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Podcast Category') }}</span>
                    <span class="filter-control"><i class="fa fa-folder-o" aria-hidden="true"></i>
                        <select data-filter="category">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach($filterCategories as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Status') }}</span>
                    <span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"></i>
                        <select data-filter="status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="draft">{{ __('Draft') }}</option>
                        </select>
                    </span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="program-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Podcast Category') }}</th>
                    <th>{{ __('Episodes') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Added') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($programs as $program)
                    <tr data-category="{{ $program->type?->name }}" data-status="{{ $program->is_published ? 'published' : 'draft' }}" data-added="{{ $addedAt($program)?->format('Y-m-d') }}">
                        <td>
                            <div class="media-title-cell">
                                @if($program->cover_image_url)
                                    <img src="{{ $program->cover_image_url }}" alt="" class="media-table-thumb">
                                @else
                                    <span class="media-table-thumb media-table-thumb-placeholder"><i class="fa fa-image"></i></span>
                                @endif
                                <b>{{ $program->name }}</b>
                            </div>
                        </td>
                        <td class="muted" data-label="{{ __('Podcast Category') }}">{{ $program->type?->name ?? '—' }}</td>
                        <td data-label="{{ __('Episodes') }}" data-order="{{ (int) $program->media_contents_count }}">{{ $program->media_contents_count }} <small class="muted">· {{ __(':count live', ['count' => $program->episodes_count]) }}</small></td>
                        <td data-label="{{ __('Status') }}">
                            @if($program->is_published)
                                <span class="badge green">{{ __('Published') }}</span>
                            @else
                                <span class="badge slate">{{ __('Draft') }}</span>
                            @endif
                        </td>
                        <td class="muted" data-label="{{ __('Added') }}" data-order="{{ $program->created_at?->timestamp ?? 0 }}">
                            @if($program->created_at)
                                <time datetime="{{ $program->created_at->toIso8601String() }}" title="{{ $addedAt($program)->toDayDateTimeString() }}">
                                    {{ $addedAt($program)->translatedFormat('M j, Y') }}<br>
                                    <small>{{ $addedAt($program)->translatedFormat('g:i A') }}</small>
                                </time>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.programs.edit', $program) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.programs.destroy', $program) }}" onsubmit="return confirm(@js(__('Delete this podcast? Its episodes stay in Media Content but are no longer grouped under it.')))">
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

    {{-- DataTables: search, click-to-sort headers and paging over the rows above, on every tab — an empty one too, which says so
         through `emptyTable`. The strip's styles and script are shared with the Content list. --}}
    @include('cms-admin::partials.filtered-table', ['tableId' => 'program-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is) and its heading. */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #program-table .media-title-cell b { font-size: 15px; }
        #program-table td small { font-size: 11px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#program-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search podcasts…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js($activeType ? __('No podcasts in :name yet.', ['name' => $activeType->name]) : __('No podcasts yet.')),
                    zeroRecords: @js(__('No matching podcasts.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'program-filters', dateKey: 'added', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
