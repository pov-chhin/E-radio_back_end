@extends('cms-admin::layout')

@section('title', 'Article')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>Article</h1>
                {{-- One tab per category; each submits the GET form with its slug. --}}
                <form method="GET" action="{{ route('admin.music.index') }}" aria-label="Filter by category">
                    <div class="tag-view-toggle">
                        <button type="submit" name="category" value="" style="padding:7px 14px;font-size:10px" @class(['active' => ! $activeCategory])>All</button>
                        @foreach($categories as $category)
                            <button type="submit" name="category" value="{{ $category->slug }}" style="padding:7px 14px;font-size:10px" @class(['active' => $activeCategory?->is($category)])>{{ $category->name }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>Songs, plus News, Health and Economy/Finance content, by category.</p>
        </div>
        {{-- One straight link that follows the tab: "New Health" on the Health tab; All starts a Song. --}}
        @php $newCategory = $activeCategory ?? $categories->firstWhere('slug', 'song'); @endphp
        @if($newCategory)
            <a href="{{ route('admin.media-contents.create', ['category' => $newCategory->slug, 'from' => 'music']) }}" class="primary-button">New {{ $newCategory->name }} <i class="fa fa-plus" aria-hidden="true"></i></a>
        @endif
    </div>

    <div class="data-table-wrap">
        <table class="data-table" id="article-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>File size</th>
                    <th>Audio</th>
                    <th>Views</th>
                    <th>Favorites</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mediaContents as $mediaContent)
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
                        <td class="muted">{{ $mediaContent->category->name }}</td>
                        <td class="muted" data-order="{{ (int) $mediaContent->stream_size }}">{{ $mediaContent->stream_size_label }}</td>
                        <td>
                            @if($mediaContent->processing_status === 'processing')
                                <span class="badge amber">Processing</span>
                            @elseif($mediaContent->processing_status === 'failed')
                                <span class="badge slate" title="{{ $mediaContent->processing_error }}">Failed</span>
                            @else
                                <span class="badge green">Ready</span>
                            @endif
                        </td>
                        <td data-order="{{ (int) $mediaContent->play_count }}">{{ number_format($mediaContent->play_count) }}</td>
                        <td data-order="{{ (int) $mediaContent->favorite_count }}">{{ number_format($mediaContent->favorite_count) }}</td>
                        <td>
                            @if($mediaContent->status === 'public')
                                <span class="badge green">Public</span>
                            @elseif($mediaContent->status === 'scheduled')
                                <span class="badge amber" title="{{ $mediaContent->published_at_local->format('M j, Y g:ia') }}">Scheduled</span>
                            @else
                                <span class="badge slate">Private</span>
                            @endif
                        </td>
                        <td class="muted" data-order="{{ $mediaContent->published_at?->timestamp ?? 0 }}">
                            @if($mediaContent->published_at)
                                <time datetime="{{ $mediaContent->published_at->toIso8601String() }}" title="{{ $mediaContent->published_at_local->toDayDateTimeString() }}">
                                    {{ $mediaContent->published_at_local->format('M j, Y') }}<br>
                                    <small>{{ $mediaContent->published_at_local->format('g:i A') }}</small>
                                </time>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if($mediaContent->processing_status === 'ready' && $mediaContent->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $mediaContent->stream_url }}"><i class="fa fa-play"></i> Play</button>
                                @endif
                                <a href="{{ route('admin.media-contents.edit', [$mediaContent, 'from' => 'music']) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.media-contents.destroy', $mediaContent) }}" onsubmit="return confirm('Delete this article?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="from" value="music">
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="9">{{ $activeCategory ? 'Nothing in '.$activeCategory->name.' yet.' : 'Nothing here yet.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- DataTables: search, click-to-sort headers and paging over the rows above. It needs at least one real row,
         so an empty tab keeps its "Nothing here yet." line. Loaded from the same CDN as the icon font. --}}
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
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net/2.1.8/dataTables.min.js"></script>
    <script>
        if (window.jQuery && jQuery.fn.DataTable && document.querySelector('#article-table tbody tr:not(.empty-row)')) {
            jQuery('#article-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: 'Search articles…',
                    lengthMenu: '_MENU_ per page',
                    zeroRecords: 'No matching articles.',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoEmpty: '',
                    infoFiltered: '(filtered from _MAX_)',
                },
            });
        }
    </script>
@endsection
