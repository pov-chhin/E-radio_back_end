@extends('cms-admin::layout')

@section('title', 'Radio Channels')

@section('content')
    @php
        // The day each channel was added, in the admin's own timezone (what the Added column shows and the From / To filters compare).
        $addedAt = fn ($channel) => $channel->created_at?->copy()->setTimezone(config('app.admin_timezone'));
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Content') }}</p>
            <h1>{{ __('Radio Channels') }}</h1>
            <p>{{ __('Stations scraped from radio-browser.info, plus any you add manually. Review and publish manually.') }}</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            <form method="POST" action="{{ route('admin.radio-channels.sync') }}">
                @csrf
                <button type="submit" class="secondary-button">{{ __('Sync from Radio Browser') }}</button>
            </form>
            <a href="{{ route('admin.radio-channels.create') }}" class="primary-button"><i class="fa fa-plus"></i> {{ __('Add Channel') }}</a>
        </div>
    </div>

    <div class="data-table-wrap">
        {{-- Narrow the rows below by Status, Source and the day they were Added (From / To, both ends included), on top of the search box. The strip and its script are shared with the Content list. --}}
        @unless($radioChannels->isEmpty())
            <div class="table-filters" id="channel-filters" role="group" aria-label="{{ __('Filter the list') }}">
                <label class="filter-field"><span>{{ __('Status') }}</span>
                    <span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"></i>
                        <select data-filter="status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="hidden">{{ __('Hidden') }}</option>
                            <option value="draft">{{ __('Draft') }}</option>
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Source') }}</span>
                    <span class="filter-control"><i class="fa fa-globe" aria-hidden="true"></i>
                        <select data-filter="source">
                            <option value="">{{ __('All sources') }}</option>
                            <option value="radio-browser">{{ __('Radio Browser') }}</option>
                            <option value="manual">{{ __('Added manually') }}</option>
                        </select>
                    </span>
                </label>
                <label class="filter-field"><span>{{ __('Added from') }}</span>
                    <span class="filter-control"><i class="fa fa-calendar-o" aria-hidden="true"></i><input type="date" data-filter="from"></span>
                </label>
                <label class="filter-field"><span>{{ __('Added to') }}</span>
                    <span class="filter-control"><i class="fa fa-calendar-o" aria-hidden="true"></i><input type="date" data-filter="to"></span>
                </label>
                <button type="button" class="secondary-button compact filter-clear" data-filter-clear title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}" hidden><i class="fa fa-times" aria-hidden="true"></i></button>
            </div>
        @endunless
        <table class="data-table" id="channel-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Stream URL') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Added') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($radioChannels as $channel)
                    <tr data-status="{{ $channel->status }}" data-source="{{ $channel->radio_browser_uuid ? 'radio-browser' : 'manual' }}" data-added="{{ $addedAt($channel)?->format('Y-m-d') }}">
                        <td>
                            <a href="{{ route('admin.radio-channels.show', $channel) }}" class="table-name-cell" style="text-decoration:none;color:inherit">
                                @if($channel->cover)
                                    <img class="table-avatar" src="{{ $channel->cover_url }}" alt="">
                                @else
                                    <span class="table-avatar-fallback">{{ strtoupper(substr($channel->name, 0, 2)) }}</span>
                                @endif
                                <b>{{ $channel->name }}</b>
                            </a>
                        </td>
                        <td class="muted stream-url" data-label="{{ __('Stream URL') }}" @if($channel->stream_url) title="{{ $channel->stream_url }}" @endif>{{ $channel->stream_url ?? '—' }}</td>
                        <td data-label="{{ __('Status') }}">
                            @if($channel->status === 'published')
                                <span class="badge green">{{ __('published') }}</span>
                            @elseif($channel->status === 'hidden')
                                <span class="badge slate">{{ __('hidden') }}</span>
                            @else
                                <span class="badge amber">{{ __('Draft (no stream URL)') }}</span>
                            @endif
                        </td>
                        <td class="muted" data-label="{{ __('Added') }}" data-order="{{ $channel->created_at?->timestamp ?? 0 }}">
                            @if($channel->created_at)
                                <time datetime="{{ $channel->created_at->toIso8601String() }}" title="{{ $addedAt($channel)->toDayDateTimeString() }}">
                                    {{ $addedAt($channel)->translatedFormat('M j, Y') }}<br>
                                    <small>{{ $addedAt($channel)->translatedFormat('g:i A') }}</small>
                                </time>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if($channel->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $channel->stream_url }}"><i class="fa fa-play"></i> {{ __('Play') }}</button>
                                    <form method="POST" action="{{ route('admin.radio-channels.checkSignal', $channel) }}">
                                        @csrf
                                        <button type="submit" class="secondary-button compact">{{ __('Check Signal') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.radio-channels.togglePublish', $channel) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="secondary-button compact">
                                        {{ $channel->is_published ? __('Hide') : __('Publish') }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.radio-channels.edit', $channel) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.radio-channels.destroy', $channel) }}" onsubmit="return confirm(@js(__('Delete this channel?')))">
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
         The strip's styles and script are shared with the Content list. --}}
    @include('cms-admin::partials.filtered-table', ['tableId' => 'channel-table'])
    <style>
        /* This page's own sizes (the type scale is 15 / 13 / 11px; the page's big title is left as it is). */
        .page-heading > div > p:last-child { font-size: 15px; }
        .page-heading .eyebrow { font-size: 11px; }
        #channel-table .table-name-cell b { font-size: 15px; }
        #channel-table td time small { font-size: 11px; }
        /* A long stream address is cut short (the whole of it is the tooltip), so it doesn't stretch the table. */
        #channel-table td.stream-url { max-width: 340px; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 760px) { #channel-table td.stream-url { max-width: none; word-break: break-all; } }
    </style>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const table = jQuery('#channel-table').DataTable({
                order: [],                                   // keep the newest-first order until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // the Actions column
                language: {
                    search: '',
                    searchPlaceholder: @js(__('Search channels…')),
                    lengthMenu: @js(__('_MENU_ per page')),
                    emptyTable: @js(__('No channels yet. Sync from Radio Browser or add one manually.')),
                    zeroRecords: @js(__('No matching channels.')),
                    info: @js(__('Showing _START_–_END_ of _TOTAL_')),
                    infoEmpty: '',
                    infoFiltered: @js(__('(filtered from _MAX_)')),
                },
            });

            attachTableFilters(table, { filtersId: 'channel-filters', dateKey: 'added', searchLabel: @js(__('Search')) });
        }
    </script>
@endsection
