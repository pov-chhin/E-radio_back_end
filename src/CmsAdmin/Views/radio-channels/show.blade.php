@extends('cms-admin::layout')

@section('title', $radioChannel->name)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Network</p>
            <h1>{{ $radioChannel->name }}</h1>
            <p>{{ $radioChannel->description ?: 'Channel details, stream info, and status.' }}</p>
        </div>
        <div class="table-actions">
            @if($radioChannel->stream_url)
                <button type="button" class="secondary-button" data-preview-toggle data-stream-url="{{ $radioChannel->stream_url }}"><i class="fa fa-play"></i> Play</button>
                <form method="POST" action="{{ route('admin.radio-channels.checkSignal', $radioChannel) }}">
                    @csrf
                    <button type="submit" class="secondary-button">Check Signal</button>
                </form>
            @endif
            <a href="{{ route('admin.radio-channels.edit', $radioChannel) }}" class="primary-button">Edit</a>
        </div>
    </div>

    @if($radioChannel->cover)
        <div class="panel" style="overflow:hidden;margin-bottom:17px">
            <img src="{{ $radioChannel->cover_url }}" alt="" style="width:100%;height:auto;max-height:480px;object-fit:contain;display:block">
        </div>
    @endif

    <div class="dashboard-grid">
        <section class="panel" style="padding:22px">
            <div class="panel-head"><div><h3>Stream details</h3><p>What listeners connect to</p></div></div>
            <dl class="detail-list">
                <div>
                    <dt>Status</dt>
                    <dd>
                        @if($radioChannel->status === 'published')
                            <span class="badge green">published</span>
                        @elseif($radioChannel->status === 'hidden')
                            <span class="badge slate">hidden</span>
                        @else
                            <span class="badge amber">Draft (no stream URL)</span>
                        @endif
                    </dd>
                </div>
                <div><dt>Stream URL</dt><dd>{{ $radioChannel->stream_url ?? '—' }}</dd></div>
                <div><dt>Bitrate</dt><dd>{{ $radioChannel->bitrate ? number_format($radioChannel->bitrate).' kbps' : '—' }}</dd></div>
                <div><dt>Tags</dt><dd>{{ $radioChannel->tags ?? '—' }}</dd></div>
                <div><dt>Votes</dt><dd>{{ number_format($radioChannel->votes) }}</dd></div>
                <div><dt>Source</dt><dd>{{ $radioChannel->radio_browser_uuid ? 'Synced from Radio Browser' : 'Added manually' }}</dd></div>
            </dl>
        </section>
        <aside class="stacked-panels">
            <section class="panel" style="padding:20px">
                <div class="panel-head"><div><h3>Timeline</h3></div></div>
                <dl class="detail-list">
                    <div><dt>Added</dt><dd>{{ $radioChannel->created_at->format('M j, Y') }}</dd></div>
                    <div><dt>Updated</dt><dd>{{ $radioChannel->updated_at->diffForHumans() }}</dd></div>
                </dl>
            </section>
            <section class="panel" style="padding:20px">
                <div class="panel-head"><div><h3>Manage</h3></div></div>
                <div class="table-actions" style="margin-top:15px;justify-content:flex-start">
                    <form method="POST" action="{{ route('admin.radio-channels.togglePublish', $radioChannel) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="secondary-button compact">{{ $radioChannel->is_published ? 'Hide' : 'Publish' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.radio-channels.destroy', $radioChannel) }}" onsubmit="return confirm('Delete this channel?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="danger-button compact">Delete</button>
                    </form>
                </div>
            </section>
        </aside>
    </div>
@endsection
