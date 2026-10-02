@extends('cms-admin::layout')

@section('title', 'Radio Channels')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Network</p>
            <h1>Radio Channels</h1>
            <p>Stations scraped from radio-browser.info, plus any you add manually. Review and publish manually.</p>
        </div>
        <div style="display:flex;gap:10px">
            <form method="POST" action="{{ route('admin.radio-channels.sync') }}">
                @csrf
                <button type="submit" class="secondary-button">Sync from Radio Browser</button>
            </form>
            <a href="{{ route('admin.radio-channels.create') }}" class="primary-button"><i class="fa fa-plus"></i> Add Channel</a>
        </div>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Stream URL</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($radioChannels as $channel)
                    <tr>
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
                        <td class="muted">{{ $channel->stream_url ?? '—' }}</td>
                        <td>
                            @if($channel->status === 'published')
                                <span class="badge green">published</span>
                            @elseif($channel->status === 'hidden')
                                <span class="badge slate">hidden</span>
                            @else
                                <span class="badge amber">Draft (no stream URL)</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if($channel->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $channel->stream_url }}"><i class="fa fa-play"></i> Play</button>
                                    <form method="POST" action="{{ route('admin.radio-channels.checkSignal', $channel) }}">
                                        @csrf
                                        <button type="submit" class="secondary-button compact">Check Signal</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.radio-channels.togglePublish', $channel) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="secondary-button compact">
                                        {{ $channel->is_published ? 'Hide' : 'Publish' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.radio-channels.edit', $channel) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.radio-channels.destroy', $channel) }}" onsubmit="return confirm('Delete this channel?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="4">No channels yet. Sync from Radio Browser or add one manually.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $radioChannels->links() }}</div>
@endsection
