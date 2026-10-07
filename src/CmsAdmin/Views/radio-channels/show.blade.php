@extends('cms-admin::layout')

@section('title', $radioChannel->name)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Network') }}</p>
            <h1>{{ $radioChannel->name }}</h1>
            <p>{{ $radioChannel->description ?: __('Channel details, stream info, and status.') }}</p>
        </div>
        <div class="table-actions">
            @if($radioChannel->stream_url)
                <button type="button" class="secondary-button" data-preview-toggle data-stream-url="{{ $radioChannel->stream_url }}"><i class="fa fa-play"></i> {{ __('Play') }}</button>
                <form method="POST" action="{{ route('admin.radio-channels.checkSignal', $radioChannel) }}">
                    @csrf
                    <button type="submit" class="secondary-button">{{ __('Check Signal') }}</button>
                </form>
            @endif
            <a href="{{ route('admin.radio-channels.edit', $radioChannel) }}" class="primary-button">{{ __('Edit') }}</a>
        </div>
    </div>

    @if($radioChannel->cover)
        <div class="panel" style="overflow:hidden;margin-bottom:17px">
            <img src="{{ $radioChannel->cover_url }}" alt="" style="width:100%;height:auto;max-height:480px;object-fit:contain;display:block">
        </div>
    @endif

    <div class="dashboard-grid">
        <section class="panel" style="padding:22px">
            <div class="panel-head"><div><h3>{{ __('Stream details') }}</h3><p>{{ __('What listeners connect to') }}</p></div></div>
            <dl class="detail-list">
                <div>
                    <dt>{{ __('Status') }}</dt>
                    <dd>
                        @if($radioChannel->status === 'published')
                            <span class="badge green">{{ __('published') }}</span>
                        @elseif($radioChannel->status === 'hidden')
                            <span class="badge slate">{{ __('hidden') }}</span>
                        @else
                            <span class="badge amber">{{ __('Draft (no stream URL)') }}</span>
                        @endif
                    </dd>
                </div>
                <div><dt>{{ __('Stream URL') }}</dt><dd>{{ $radioChannel->stream_url ?? '—' }}</dd></div>
                <div><dt>{{ __('Bitrate') }}</dt><dd>{{ $radioChannel->bitrate ? number_format($radioChannel->bitrate).' kbps' : '—' }}</dd></div>
                <div><dt>{{ __('Tags') }}</dt><dd>{{ $radioChannel->tags ?? '—' }}</dd></div>
                <div><dt>{{ __('Votes') }}</dt><dd>{{ number_format($radioChannel->votes) }}</dd></div>
                <div><dt>{{ __('Source') }}</dt><dd>{{ $radioChannel->radio_browser_uuid ? __('Synced from Radio Browser') : __('Added manually') }}</dd></div>
            </dl>
        </section>
        <aside class="stacked-panels">
            <section class="panel" style="padding:20px">
                <div class="panel-head"><div><h3>{{ __('Timeline') }}</h3></div></div>
                <dl class="detail-list">
                    <div><dt>{{ __('Added') }}</dt><dd>{{ $radioChannel->created_at->translatedFormat('M j, Y') }}</dd></div>
                    <div><dt>{{ __('Updated') }}</dt><dd>{{ $radioChannel->updated_at->locale(app()->getLocale())->diffForHumans() }}</dd></div>
                </dl>
            </section>
            <section class="panel" style="padding:20px">
                <div class="panel-head"><div><h3>{{ __('Manage') }}</h3></div></div>
                <div class="table-actions" style="margin-top:15px;justify-content:flex-start">
                    <form method="POST" action="{{ route('admin.radio-channels.togglePublish', $radioChannel) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="secondary-button compact">{{ $radioChannel->is_published ? __('Hide') : __('Publish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.radio-channels.destroy', $radioChannel) }}" onsubmit="return confirm(@js(__('Delete this channel?')))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                    </form>
                </div>
            </section>
        </aside>
    </div>
@endsection
