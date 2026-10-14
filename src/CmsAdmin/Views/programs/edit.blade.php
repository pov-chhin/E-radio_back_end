@extends('cms-admin::layout')

@section('title', 'Edit Podcast')

@section('content')
    @php
        $canCreateEpisodes = auth('admin')->user()?->hasPermission('create-media-content');
        $canEditEpisodes = auth('admin')->user()?->hasPermission('edit-media-content');
    @endphp

    {{-- No page heading here: the bar at the top of the form carries the back arrow, the status and the title. --}}
    <form method="POST" action="{{ route('admin.programs.update', $program) }}" class="editor-form" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::programs._form')
    </form>

    <div class="page-heading" style="margin-top:32px">
        <div>
            <p class="eyebrow">{{ __('Episodes') }}</p>
            <h1 style="font-size:20px">{{ trans_choice(':count episode|:count episodes', $episodes->count()) }}</h1>
            <p>{{ __('Listeners see the public ones with audio ready, newest first.') }}</p>
        </div>
        @if($canCreateEpisodes)
            <a href="{{ route('admin.media-contents.create', ['podcast' => $program->id]) }}" class="primary-button">{{ __('Add episode') }}</a>
        @endif
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Episode') }}</th>
                    <th>{{ __('Length') }}</th>
                    <th>{{ __('Audio') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Published') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($episodes as $episode)
                    <tr>
                        <td>
                            <div class="media-title-cell">
                                @if($episode->thumbnail_url)
                                    <img src="{{ $episode->thumbnail_url }}" alt="" class="media-table-thumb">
                                @else
                                    <span class="media-table-thumb media-table-thumb-placeholder"><i class="fa fa-image"></i></span>
                                @endif
                                <span class="media-title-copy">
                                    <b>{{ $episode->title }}</b>
                                    @if($episode->artist)
                                        <small class="muted">{{ $episode->artist->stage_name ?: $episode->artist->name }}</small>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="muted">{{ $episode->duration_seconds ? intdiv($episode->duration_seconds, 60).':'.str_pad($episode->duration_seconds % 60, 2, '0', STR_PAD_LEFT) : '—' }}</td>
                        <td>
                            @if(in_array($episode->processing_status, ['processing', 'failed'], true))
                                @php $audioFailed = $episode->processing_status === 'failed'; @endphp
                                {{-- Processing and Failed rows keep themselves current: the page asks the server how the audio is doing until it is Ready or Failed (the script in the layout). --}}
                                <span class="badge {{ $audioFailed ? 'slate' : 'amber' }}" data-status-badge data-state="{{ $episode->processing_status }}" data-status-url="{{ route('admin.media-contents.processing-status', $episode) }}" data-labels="{{ json_encode(['processing' => __('Processing'), 'ready' => __('Ready'), 'failed' => __('Failed')], JSON_UNESCAPED_UNICODE) }}" @if($audioFailed) title="{{ $episode->processing_error }}" @endif>{{ $audioFailed ? __('Failed') : __('Processing') }}</span>
                                @if(auth('admin')->user()?->hasPermission('edit-media-content'))
                                    <form method="POST" action="{{ route('admin.media-contents.retry-audio', $episode) }}" data-retry-audio style="display:{{ $audioFailed ? 'inline' : 'none' }}">
                                        @csrf
                                        <button type="submit" class="secondary-button compact" style="margin-left:6px">{{ __('Retry') }}</button>
                                    </form>
                                @endif
                            @elseif(! $episode->stream_url)
                                <span class="badge slate">{{ __('No audio') }}</span>
                            @else
                                <span class="badge green">{{ __('Ready') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($episode->status === 'public')
                                <span class="badge green">{{ __('Public') }}</span>
                            @elseif($episode->status === 'scheduled')
                                <span class="badge amber" title="{{ $episode->published_at_local->translatedFormat('M j, Y g:ia') }}">{{ __('Scheduled') }}</span>
                            @else
                                <span class="badge slate">{{ __('Private') }}</span>
                            @endif
                        </td>
                        <td class="muted">{{ $episode->published_at_local?->translatedFormat('M j, Y') ?? '—' }}</td>
                        <td>
                            <div class="table-actions">
                                @if($episode->processing_status === 'ready' && $episode->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $episode->stream_url }}"><i class="fa fa-play"></i> {{ __('Play') }}</button>
                                @endif
                                @if($canEditEpisodes)
                                    <a href="{{ route('admin.media-contents.edit', [$episode, 'podcast' => $program->id]) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="6">{{ __('No episodes yet.') }}@if($canCreateEpisodes) {{ __('Use “Add episode” to upload the first one.') }}@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
