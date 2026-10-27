@extends('cms-admin::layout')

@section('title', 'Radio Channels')

@section('content')
    @php
        // The day each channel was added, in the admin's own timezone (what the Added column shows and the From / To filters compare).
        $addedAt = fn ($channel) => $channel->created_at?->copy()->setTimezone(config('app.admin_timezone'));
        // Whoever may edit a channel may also drag the rows into a new order (each drop is saved at once).
        $canReorder = auth('admin')->user()?->hasPermission('edit-radio-channels') ?? false;
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
        {{-- Narrow the rows below by Status and Source, on top of the search box (no date boxes: the shared script also takes from / to ones). The strip and its script are shared with the Content list. --}}
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
                    <th>{{ __('Position') }}</th>   {{-- never shown: the place in the list, which the rows are kept in until a header is clicked --}}
                </tr>
            </thead>
            <tbody>
                @foreach($radioChannels as $channel)
                    <tr data-status="{{ $channel->status }}" data-source="{{ $channel->radio_browser_uuid ? 'radio-browser' : 'manual' }}" data-added="{{ $addedAt($channel)?->format('Y-m-d') }}">
                        <td>
                            <div class="channel-name">
                                @if($canReorder)
                                    <span class="drag-handle" data-id="{{ $channel->id }}" title="{{ __('Drag to change the order') }}" aria-label="{{ __('Drag to change the order') }}"><i class="fa fa-bars" aria-hidden="true"></i></span>
                                @endif
                                <a href="{{ route('admin.radio-channels.show', $channel) }}" class="table-name-cell" style="text-decoration:none;color:inherit">
                                    @if($channel->cover)
                                        <img class="table-avatar" src="{{ $channel->cover_url }}" alt="">
                                    @else
                                        <span class="table-avatar-fallback">{{ strtoupper(substr($channel->name, 0, 2)) }}</span>
                                    @endif
                                    <b>{{ $channel->name }}</b>
                                </a>
                            </div>
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
                        <td>{{ $loop->index }}</td>
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
        #channel-table .table-name-cell b { font-size: 15px; }        #channel-table td time small { font-size: 11px; }
        /* A long stream address is cut short (the whole of it is the tooltip), so it doesn't stretch the table. */
        #channel-table td.stream-url { max-width: 340px; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 760px) { #channel-table td.stream-url { max-width: none; word-break: break-all; } }
        /* The grip at the left of a name: grab it and drop the row where it should be listed. Faded (and a plain click) while a header's sort hides the saved order. */
        #channel-table .channel-name { display: flex; align-items: center; gap: 10px; }
        #channel-table .drag-handle { flex: none; padding: 6px 4px; font-size: 15px; color: #9598a6; cursor: grab; touch-action: none; user-select: none; }
        #channel-table .drag-handle:hover { color: var(--pink); }
        #channel-table.is-column-sorted .drag-handle { opacity: .35; cursor: pointer; }
        #channel-table tr.row-ghost { background: #fff7f9; opacity: .6; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
        if (window.jQuery && jQuery.fn.DataTable) {
            const POSITION = 5;                              // the hidden column: where the row stands in the list
            const table = jQuery('#channel-table').DataTable({
                order: [[POSITION, 'asc']],                  // the order the app lists them in, until a header is clicked
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [
                    { targets: 4, orderable: false, searchable: false },                               // the Actions column
                    { targets: POSITION, visible: false, searchable: false, type: 'num' },
                ],
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

            @if($canReorder)
            // Drag a row by its grip and drop it next to another row: the new order is saved at once, nothing to press. The row is
            // sent with the row it landed beside (before it, or after it when it is last on the page) and the server places it in the
            // whole list — so it works with a search or a filter on, and the rows that are hidden keep their places. It can only
            // move within the page shown (set "per page" higher to carry a row further). While a header's sort is on, dragging is off.
            const idOf = node => node.querySelector('.drag-handle')?.dataset.id;
            const nodes = new Map();                         // id => the row's <tr>, kept whichever page it is on
            const order = [];                                // every id, in the saved order
            table.rows({ order: 'index' }).nodes().each(node => { const id = idOf(node); nodes.set(id, node); order.push(id); });

            const tableNode = table.table().node();
            const toast = (message, isError = false) => {
                const element = document.createElement('div');
                element.className = 'toast';
                element.innerHTML = '<span><i class="fa ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i></span>';
                element.append(message);
                document.querySelector('.page-wrap')?.before(element);
                setTimeout(() => element.remove(), 3000);
            };

            let saving = Promise.resolve();                  // one save at a time, in the order the drops happened
            const save = (id, target, placement) => {
                saving = saving.then(async () => {
                    try {
                        const response = await fetch(@js(route('admin.radio-channels.reorder')), {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ id, target, placement }),
                        });
                        if (!response.ok) throw new Error(response.status);
                        toast(@js(__('Order saved.')));
                    } catch (error) {
                        // What this page shows no longer matches what is saved: start again from the saved list.
                        toast(@js(__('Could not save the new order. Reloading the list…')), true);
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            };

            const inSavedOrder = () => { const current = table.order(); return current.length === 1 && current[0][0] === POSITION && current[0][1] === 'asc'; };
            const sortable = window.Sortable && Sortable.create(table.table().body(), {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'row-ghost',
                onEnd: event => {
                    const row = event.item;
                    if (event.oldIndex === event.newIndex) return;
                    const next = row.nextElementSibling;
                    const neighbour = next ?? row.previousElementSibling;
                    if (!neighbour) return;
                    const id = idOf(row), target = idOf(neighbour), placement = next ? 'before' : 'after';

                    // Keep the table's own idea of the order in step, so paging, searching and filtering show the same thing.
                    const from = order.indexOf(id);
                    order.splice(from, 1);
                    const to = order.indexOf(target) + (placement === 'after' ? 1 : 0);
                    order.splice(to, 0, id);
                    for (let place = Math.min(from, to); place <= Math.max(from, to); place++) {
                        table.cell(nodes.get(order[place]), POSITION).data(place);
                    }
                    table.draw(false);

                    save(id, target, placement);
                },
            });

            // Clicking a header sorts by that column, which hides the saved order: dragging is off until the grip is clicked to go back.
            const syncSorting = () => {
                const saved = inSavedOrder();
                sortable?.option('disabled', !saved);
                tableNode.classList.toggle('is-column-sorted', !saved);
                tableNode.querySelectorAll('.drag-handle').forEach(handle => {
                    handle.title = saved ? @js(__('Drag to change the order')) : @js(__('Sorted by a column — click to go back to the saved order'));
                });
            };
            table.on('order.dt draw.dt', syncSorting);
            tableNode.addEventListener('click', event => {
                if (event.target.closest('.drag-handle') && !inSavedOrder()) table.order([[POSITION, 'asc']]).draw();
            });
            @endif
        }
    </script>
@endsection
