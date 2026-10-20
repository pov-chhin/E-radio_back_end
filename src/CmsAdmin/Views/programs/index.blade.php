@extends('cms-admin::layout')

@section('title', 'Podcasts')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Content') }}</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>{{ __('Podcasts') }}</h1>
                {{-- One tab per podcast type, taken from the tags under Podcast; each submits the GET form with its slug. --}}
                <form method="GET" action="{{ route('admin.programs.index') }}" aria-label="{{ __('Filter by podcast category') }}">
                    <div class="tag-view-toggle">
                        <button type="submit" name="type" value="" style="padding:7px 14px;font-size:10px" @class(['active' => ! $activeType])>{{ __('All') }}</button>
                        @foreach($types as $type)
                            <button type="submit" name="type" value="{{ $type->slug }}" style="padding:7px 14px;font-size:10px" @class(['active' => $activeType?->is($type)])>{{ $type->name }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>{{ __('Shows and their episodes, as listeners see them in the app.') }}</p>
        </div>
        {{-- One straight link to the create page; the podcast type is picked on the form (an open type tab is already selected there). --}}
        <a href="{{ route('admin.programs.create', $activeType ? ['type' => $activeType->slug] : []) }}" class="primary-button">{{ __('New Podcast Program') }} <i class="fa fa-plus" aria-hidden="true"></i></a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Podcast Category') }}</th>
                    <th>{{ __('Episodes') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $program)
                    <tr>
                        <td>
                            <div class="media-title-cell">
                                @if($program->cover_image_url)
                                    <img src="{{ $program->cover_image_url }}" alt="" class="media-table-thumb">
                                @else
                                    <span class="media-table-thumb media-table-thumb-placeholder"><i class="fa fa-image"></i></span>
                                @endif
                                <b>{{ $program->name }}</b>
                            </div>
                        </td>
                        <td class="muted">{{ $program->type?->name ?? '—' }}</td>
                        <td>{{ $program->media_contents_count }} <small class="muted">· {{ __(':count live', ['count' => $program->episodes_count]) }}</small></td>
                        <td>
                            @if($program->is_published)
                                <span class="badge green">{{ __('Published') }}</span>
                            @else
                                <span class="badge slate">{{ __('Draft') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.programs.edit', $program) }}" class="secondary-button compact">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.programs.destroy', $program) }}" onsubmit="return confirm(@js(__('Delete this podcast? Its episodes stay in Media Content but are no longer grouped under it.')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">{{ $activeType ? __('No podcasts in :name yet.', ['name' => $activeType->name]) : __('No podcasts yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $programs->links() }}</div>
@endsection
