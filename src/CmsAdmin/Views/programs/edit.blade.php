@extends('cms-admin::layout')

@section('title', 'Edit Podcast')

@section('content')
    @php
        $canCreateEpisodes = auth('admin')->user()?->hasPermission('create-media-content');
        $canEditEpisodes = auth('admin')->user()?->hasPermission('edit-media-content');
    @endphp

    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Edit Podcast</h1>
            <p>Update {{ $program->name }}.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.programs.update', $program) }}" class="panel form-card" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::programs._form')
    </form>

    <div class="page-heading" style="margin-top:32px">
        <div>
            <p class="eyebrow">Episodes</p>
            <h1 style="font-size:20px">{{ $episodes->count() }} {{ Str::plural('episode', $episodes->count()) }}</h1>
            <p>Listeners see the public ones with audio ready, newest first.</p>
        </div>
        @if($canCreateEpisodes)
            <a href="{{ route('admin.media-contents.create', ['podcast' => $program->id]) }}" class="primary-button">Add episode</a>
        @endif
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Episode</th>
                    <th>Length</th>
                    <th>Audio</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th class="right">Actions</th>
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
                            @if($episode->processing_status === 'processing')
                                <span class="badge amber">Processing</span>
                            @elseif($episode->processing_status === 'failed')
                                <span class="badge slate" title="{{ $episode->processing_error }}">Failed</span>
                            @elseif(! $episode->stream_url)
                                <span class="badge slate">No audio</span>
                            @else
                                <span class="badge green">Ready</span>
                            @endif
                        </td>
                        <td>
                            @if($episode->status === 'public')
                                <span class="badge green">Public</span>
                            @elseif($episode->status === 'scheduled')
                                <span class="badge amber" title="{{ $episode->published_at_local->format('M j, Y g:ia') }}">Scheduled</span>
                            @else
                                <span class="badge slate">Private</span>
                            @endif
                        </td>
                        <td class="muted">{{ $episode->published_at_local?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            <div class="table-actions">
                                @if($episode->processing_status === 'ready' && $episode->stream_url)
                                    <button type="button" class="secondary-button compact" data-preview-toggle data-stream-url="{{ $episode->stream_url }}"><i class="fa fa-play"></i> Play</button>
                                @endif
                                @if($canEditEpisodes)
                                    <a href="{{ route('admin.media-contents.edit', [$episode, 'podcast' => $program->id]) }}" class="secondary-button compact">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="6">No episodes yet.@if($canCreateEpisodes) Use “Add episode” to upload the first one.@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
