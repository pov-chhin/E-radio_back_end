@extends('cms-admin::layout')

@section('title', 'Content')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Content') }}</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>{{ __('Content') }}</h1>
                {{-- One tab per group (Song; Audio = News / Health / Economy/Finance; Podcasts); each submits the GET form with its key.
                     The tab bar is hidden for now (the filters below narrow the list); the tabs and their ?category= addresses still work. --}}
                <form method="GET" action="{{ route('admin.music.index') }}" aria-label="{{ __('Filter by category') }}" hidden>
                    <div class="tag-view-toggle">
                        <button type="submit" name="category" value="" style="padding:7px 14px;font-size:13px" @class(['active' => ! $activeTab])>{{ __('All') }}</button>
                        @foreach($tabs as $tab)
                            <button type="submit" name="category" value="{{ $tab['key'] }}" style="padding:7px 14px;font-size:13px" @class(['active' => $activeTab && $activeTab['key'] === $tab['key']])>{{ $tab['label'] }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>{{ __('Songs, Audio (News, Health and Economy/Finance) and Podcasts.') }}</p>
        </div>
        {{-- "New" opens a menu with one entry per tab, each with its icon: Song, Audio (it opens on News; the form's category list picks Health or Economy/Finance), Podcast Episode and Ambient Music. The tab on show is marked. --}}
        <div class="dropdown-anchor">
            <button type="button" class="primary-button" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>{{ __('New') }} <i class="fa fa-plus" aria-hidden="true"></i></button>
            <div class="dropdown-menu dropdown-right new-menu" data-dropdown>
                @foreach($tabs as $tab)
                    <a href="{{ route('admin.media-contents.create', ['category' => $tab['new']]) }}" @class(['active' => $activeTab && $activeTab['key'] === $tab['key']])><i class="fa {{ $tab['icon'] }}" aria-hidden="true"></i>{{ $tab['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>

    @php
        // "All" names each row's group (Song, Audio, Podcasts, Ambient Music) rather than News / Health / Economy/Finance; a tab shows
        // the category itself. The Category column and its filter say the same.
        $categoryLabel = fn ($item) => $activeTab ? $item->category->name : ($groupLabels[$item->category->slug] ?? $item->category->name);
        $filterCategories = $mediaContents->map($categoryLabel)->unique()->sort()->values();
    @endphp
    <div class="data-table-wrap">
        {{-- Narrow the rows below by Category, Status and Region (from the item's tags and artists), on top of the search box, as soon as a box is changed (no date boxes: the shared script also takes from / to ones). --}}
        @unless($mediaContents->isEmpty())
            <div class="table-filters" id="article-filters" role="group" aria-label="{{ __('Filter the list') }}">
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
                <label class="filter-field"><span>{{ __('Status') }}</span>
                    <span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"></i>
                        <select data-filter="status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="public">{{ __('Public') }}</option>
                            <option value="scheduled">{{ __('Scheduled') }}</option>
                            <option value="private">{{ __('Private') }}</option>
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
        <table class="data-table" id="article-table">
            <thead>
                <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('File size') }}</th>
                    <th>{{ __('Audio') }}</th>
                    <th>{{ __('Views') }}</th>
                    <th>{{ __('Favorites') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Published') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mediaContents as $mediaContent)
                    @php
                        // Long titles show their first 25 letters and "...", the full title on hover. Cut on whole letters
                        // (a Khmer letter is several characters), never leaving a dangling coeng (U+17D2) before the dots.
                        preg_match_all('/\X/u', (string) $mediaContent->title, $letters);
                        $letters = $letters[0] ?? [];
                        $shortTitle = $mediaContent->title;
                        if (count($letters) > 25) {
                            $shortTitle = rtrim(implode('', array_slice($letters, 0, 25)));
                            $shortTitle = rtrim(preg_replace('/\x{17D2}$/u', '', $shortTitle)).'...';
                        }
                        $people = collect([$mediaContent->artist_names, $mediaContent->author?->name])->filter()->implode(' · ');
                    @endphp
                    <tr data-category="{{ $categoryLabel($mediaContent) }}" data-status="{{ $mediaContent->status }}" data-published="{{ $mediaContent->published_at_local?->format('Y-m-d') }}" data-region="{{ $regions[$mediaContent->id] }}">
                        {{-- The table sorts and searches the full title (and names), not the shortened text on show. --}}
                        <td data-order="{{ $mediaContent->title }}" data-search="{{ trim($mediaContent->title.' '.$people) }}">
                            <div class="media-title-cell">
                                @if($mediaContent->thumbnail_url)
                                    <img src="{{ $mediaContent->thumbnail_url }}" alt="" class="media-table-thumb">
                                @else
                                    <span class="media-table-thumb media-table-thumb-placeholder"><i class="fa fa-image"></i></span>
                                @endif
                                <span class="media-title-copy">
                                    <b @if($shortTitle !== $mediaContent->title)title="{{ $mediaContent->title }}"@endif>{{ $shortTitle }}</b>
                                    @if($people !== '')
                                        <small class="muted">{{ $people }}</small>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="muted" data-label="{{ __('Category') }}">{{ $categoryLabel($mediaContent) }}</td>
                        <td class="muted" data-label="{{ __('File size') }}" data-order="{{ (int) $mediaContent->stream_size }}">{{ $mediaContent->stream_size_label }}</td>
                        <td data-label="{{ __('Audio') }}">
                            @if(in_array($mediaContent->processing_status, ['processing', 'failed'], true))
                                @php $audioFailed = $mediaContent->processing_status === 'failed'; @endphp
                                {{-- Processing and Failed rows keep themselves current: the page asks the server how the audio is doing until it is Ready or Failed (the script in the layout). --}}
                                <span class="badge {{ $audioFailed ? 'slate' : 'amber' }}" data-status-badge data-state="{{ $mediaContent->processing_status }}" data-status-url="{{ route('admin.media-contents.processing-status', $mediaContent) }}" data-labels="{{ json_encode(['processing' => __('Processing'), 'ready' => __('Ready'), 'failed' => __('Failed')], JSON_UNESCAPED_UNICODE) }}" @if($audioFailed) title="{{ $mediaContent->processing_error }}" @endif>{{ $audioFailed ? __('Failed') : __('Processing') }}</span>
                                @if(auth('admin')->user()?->hasPermission('edit-media-content'))
                                    <form method="POST" action="{{ route('admin.media-contents.retry-audio', $mediaContent) }}" data-retry-audio style="display:{{ $audioFailed ? 'inline' : 'none' }}">
                                        @csrf
                                        <button type="submit" class="secondary-button compact" style="margin-left:6px">{{ __('Retry') }}</button>
                                    </form>
                                @endif
                            @else
                                <span class="badge green">{{ __('Ready') }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('Views') }}" data-order="{{ (int) $mediaContent->play_count }}">{{ number_format($mediaContent->play_count) }}</td>
                        <td data-label="{{ __('Favorites') }}" data-order="{{ (int) $mediaContent->favorite_count }}">{{ number_format($mediaContent->favorite_count) }}</td>
                        <td data-label="{{ __('Status') }}">
                            @if($mediaContent->status === 'public')
                                <span class="badge green">{{ __('Public') }}</span>
                            @elseif($mediaContent->status === 'scheduled')
                                <span class="badge amber" title="{{ $mediaContent->published_at_local->translatedFormat('M j, Y g:ia') }}">{{ __('Scheduled') }}</span>
                            @else
                                <span class="badge slate">{{ __('Private') }}</span>
                            @endif
                        </td>
                        <td class="muted" data-label="{{ __('Published') }}" data-order="{{ $mediaContent->published_at?->timestamp ?? 0 }}">
                            @if($mediaContent->published_at)
                                <time datetime="{{ $mediaContent->published_at->toIso8601String() }}" title="{{ $mediaContent->published_at_local->toDayDateTimeString() }}">
                                    {{ $mediaContent->published_at_local->translatedFormat('M j, Y') }}<br>
                                    <small>{{ $mediaContent->published_at_local->translatedFormat('g:i A') }}</small>
                                </time>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if($mediaContent->processing_status === 'ready' && $mediaContent->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $mediaContent->stream_url }}"><i class="fa fa-play"></i> {{ __('Play') }}</button>
                                @endif
                                <a href="{{ route('admin.media-contents.edit-in', ['category' => $mediaContent->category->slug, 'media_content' => $mediaContent]) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.media-contents.destroy', $mediaContent) }}" onsubmit="return confirm(@js(__('Delete this article?')))">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="from" value="music">
                                    <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- DataTables: search, click-to-sort headers and paging over the rows above, on every tab — an empty one
         too, which says so through `emptyTable`. The strip's styles and script are shared with the other filtered lists. --}}
    @include('cms-admin::partials.filtered-table', ['tableId' => 'article-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is) and its heading. */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        .new-menu a { font-size: 15px; }
        .media-title-copy b { font-size: 15px; }
        .media-title-copy small, #article-table td time small { font-size: 11px; }
        @media (max-width: 760px) { .page-heading .primary-button { align-self: flex-start; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#article-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search articles…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js($activeTab ? __('Nothing in :name yet.', ['name' => $activeTab['label']]) : __('Nothing here yet.')),
                    zeroRecords: @js(__('No matching articles.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'article-filters', dateKey: 'published', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
