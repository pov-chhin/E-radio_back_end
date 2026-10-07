@extends('cms-admin::layout')

@section('title', 'Media Content')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Media Content') }}</h1>
            <p>{{ __('Songs, episodes, articles, and everything else listeners can play or read.') }}</p>
        </div>
        <a href="{{ route('admin.media-contents.create') }}" class="primary-button">{{ __('New media content') }}</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Program') }}</th>
                    <th>{{ __('Type') }}</th>
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
                        <td class="muted">{{ $mediaContent->program->name ?? '—' }}</td>
                        <td><span class="badge violet">{{ __(ucfirst($mediaContent->media_type)) }}</span></td>
                        <td class="muted">{{ $mediaContent->stream_size_label }}</td>
                        <td>
                            @if($mediaContent->processing_status === 'processing')
                                <span class="badge amber">{{ __('Processing') }}</span>
                            @elseif($mediaContent->processing_status === 'failed')
                                <span class="badge slate" title="{{ $mediaContent->processing_error }}">{{ __('Failed') }}</span>
                            @else
                                <span class="badge green">{{ __('Ready') }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($mediaContent->play_count) }}</td>
                        <td>{{ number_format($mediaContent->favorite_count) }}</td>
                        <td>
                            @if($mediaContent->status === 'public')
                                <span class="badge green">{{ __('Public') }}</span>
                            @elseif($mediaContent->status === 'scheduled')
                                <span class="badge amber" title="{{ $mediaContent->published_at_local->translatedFormat('M j, Y g:ia') }}">{{ __('Scheduled') }}</span>
                            @else
                                <span class="badge slate">{{ __('Private') }}</span>
                            @endif
                        </td>
                        <td class="muted">
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
                                <a href="{{ route('admin.media-contents.edit', $mediaContent) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.media-contents.destroy', $mediaContent) }}" onsubmit="return confirm(@js(__('Delete this media content?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="11">{{ __('No media content yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $mediaContents->links() }}</div>
@endsection
