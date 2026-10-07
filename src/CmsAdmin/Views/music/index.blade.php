@extends('cms-admin::layout')

@section('title', 'Article')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Catalog') }}</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>{{ __('Article') }}</h1>
                {{-- One tab per group (Song; Program = News / Health / Economy/Finance; Podcasts); each submits the GET form with its key. --}}
                <form method="GET" action="{{ route('admin.music.index') }}" aria-label="{{ __('Filter by category') }}">
                    <div class="tag-view-toggle">
                        <button type="submit" name="category" value="" style="padding:7px 14px;font-size:10px" @class(['active' => ! $activeTab])>{{ __('All') }}</button>
                        @foreach($tabs as $tab)
                            <button type="submit" name="category" value="{{ $tab['key'] }}" style="padding:7px 14px;font-size:10px" @class(['active' => $activeTab && $activeTab['key'] === $tab['key']])>{{ $tab['label'] }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>{{ __('Songs, Program (News, Health and Economy/Finance) and Podcasts.') }}</p>
        </div>
        {{-- One straight link that follows the tab: "New Program" on the Program tab (it opens on News; the form's category list picks Health or Economy/Finance); All starts a Song. --}}
        <a href="{{ route('admin.media-contents.create', ['category' => $newTab['new']]) }}" class="primary-button">{{ __('New :name', ['name' => $newTab['new_name']]) }} <i class="fa fa-plus" aria-hidden="true"></i></a>
    </div>

    <div class="data-table-wrap">
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
                    <tr>
                        <td>
                            <div class="media-title-cell">
                                @if($mediaContent->thumbnail_url)
                                    <img src="{{ $mediaContent->thumbnail_url }}" alt="" class="media-table-thumb">
                                @else
                                    <span class="media-table-thumb media-table-thumb-placeholder"><i class="fa fa-image"></i></span>
                                @endif
                                <span class="media-title-copy">
                                    <b>{{ $mediaContent->title }}</b>
                                    @if($mediaContent->artist || $mediaContent->author)
                                        <small class="muted">{{ collect([$mediaContent->artist?->stage_name ?: $mediaContent->artist?->name, $mediaContent->author?->name])->filter()->implode(' · ') }}</small>
                                    @endif
                                </span>
                            </div>
                        </td>
                        {{-- "All" names the group (Song, Program, Podcasts, Ambient Music) rather than News / Health / Economy/Finance; a tab shows the category itself. --}}
                        <td class="muted">{{ $activeTab ? $mediaContent->category->name : ($groupLabels[$mediaContent->category->slug] ?? $mediaContent->category->name) }}</td>
                        <td class="muted" data-order="{{ (int) $mediaContent->stream_size }}">{{ $mediaContent->stream_size_label }}</td>
                        <td>
                            @if($mediaContent->processing_status === 'processing')
                                <span class="badge amber">{{ __('Processing') }}</span>
                            @elseif($mediaContent->processing_status === 'failed')
                                <span class="badge slate" title="{{ $mediaContent->processing_error }}">{{ __('Failed') }}</span>
                            @else
                                <span class="badge green">{{ __('Ready') }}</span>
                            @endif
                        </td>
                        <td data-order="{{ (int) $mediaContent->play_count }}">{{ number_format($mediaContent->play_count) }}</td>
                        <td data-order="{{ (int) $mediaContent->favorite_count }}">{{ number_format($mediaContent->favorite_count) }}</td>
                        <td>
                            @if($mediaContent->status === 'public')
                                <span class="badge green">{{ __('Public') }}</span>
                            @elseif($mediaContent->status === 'scheduled')
                                <span class="badge amber" title="{{ $mediaContent->published_at_local->translatedFormat('M j, Y g:ia') }}">{{ __('Scheduled') }}</span>
                            @else
                                <span class="badge slate">{{ __('Private') }}</span>
                            @endif
                        </td>
                        <td class="muted" data-order="{{ $mediaContent->published_at?->timestamp ?? 0 }}">
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
         too, which says so through `emptyTable`. Loaded from the same CDN as the icon font. --}}
    <style>
        #article-table_wrapper .dt-layout-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px 16px; padding: 12px 18px; }
        #article-table_wrapper .dt-layout-row.dt-layout-table { display: block; padding: 0; }
        #article-table_wrapper .dt-layout-cell { display: flex; align-items: center; gap: 8px; }
        #article-table_wrapper .dt-layout-table .dt-layout-cell { display: block; }
        #article-table_wrapper label { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 11px; font-weight: 600; color: #747785; }
        #article-table_wrapper .dt-search input, #article-table_wrapper .dt-length select { height: 34px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: #fff; font-size: 12px; }
        #article-table_wrapper .dt-search input { min-width: 220px; }
        #article-table_wrapper .dt-info { font-size: 11px; color: #8b8fa3; }
        #article-table_wrapper .dt-paging { display: flex; gap: 4px; }
        #article-table_wrapper .dt-paging-button { min-width: 30px; height: 30px; padding: 0 9px; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-size: 11px; font-weight: 700; color: #3d3f4d; cursor: pointer; }
        #article-table_wrapper .dt-paging-button.current { background: var(--pink); border-color: var(--pink); color: #fff; }
        #article-table_wrapper .dt-paging-button.disabled { opacity: .4; cursor: default; }
        #article-table th.dt-orderable-asc, #article-table th.dt-orderable-desc { cursor: pointer; }
        #article-table th .dt-column-header { display: inline-flex; align-items: center; gap: 6px; }
        #article-table th .dt-column-order::after { content: '\2195'; opacity: .35; font-size: 10px; }
        #article-table th.dt-ordering-asc .dt-column-order::after { content: '\25B2'; opacity: 1; }
        #article-table th.dt-ordering-desc .dt-column-order::after { content: '\25BC'; opacity: 1; }
        #article-table th.dt-orderable-none .dt-column-order { display: none; }
        /* DataTables' own "nothing here" cell: centred across the table, like the empty row it replaces. */
        #article-table td.dt-empty { text-align: center; padding: 54px 18px; color: var(--muted); }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net/2.1.8/dataTables.min.js"></script>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            jQuery('#article-table').DataTable({
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
        }
    </script>
@endsection
