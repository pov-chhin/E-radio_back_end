@extends('cms-admin::layout')

@section('title', 'Podcasts')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 20px">
                <h1>Podcasts</h1>
                {{-- One tab per podcast type, taken from the tags under Podcast; each submits the GET form with its slug. --}}
                <form method="GET" action="{{ route('admin.programs.index') }}" aria-label="Filter by podcast type">
                    <div class="tag-view-toggle">
                        <button type="submit" name="type" value="" style="padding:7px 14px;font-size:10px" @class(['active' => ! $activeType])>All</button>
                        @foreach($types as $type)
                            <button type="submit" name="type" value="{{ $type->slug }}" style="padding:7px 14px;font-size:10px" @class(['active' => $activeType?->is($type)])>{{ $type->name }}</button>
                        @endforeach
                    </div>
                </form>
            </div>
            <p>Shows and their episodes, as listeners see them in the app.</p>
        </div>
        {{-- "New +" opens the list of categories; picking one starts the new program in it. --}}
        <details class="program-new-menu" style="position:relative">
            <summary class="primary-button" style="list-style:none;cursor:pointer" aria-haspopup="menu">New <i class="fa fa-plus" aria-hidden="true"></i></summary>
            <div role="menu" aria-label="Select a podcast type for the new podcast" style="position:absolute;right:0;top:calc(100% + 8px);z-index:20;min-width:210px;padding:6px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 28px rgba(25,25,50,.14)">
                <p class="muted" style="margin:0;padding:8px 12px 6px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Select podcast type</p>
                @foreach($types as $type)
                    <a role="menuitem" href="{{ route('admin.programs.create', ['type' => $type->slug]) }}">{{ $type->name }}</a>
                @endforeach
            </div>
        </details>
    </div>

    <style>
        .program-new-menu > summary::-webkit-details-marker { display: none; }
        .program-new-menu a[role=menuitem] { display: block; padding: 9px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; color: inherit; text-decoration: none; }
        .program-new-menu a[role=menuitem]:hover { background: #f4f4f8; }
    </style>
    <script>
        // Close the menu when something else is clicked.
        document.addEventListener('click', event => {
            document.querySelectorAll('.program-new-menu[open]').forEach(menu => {
                if (!menu.contains(event.target)) menu.open = false;
            });
        });
    </script>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Podcast type</th>
                    <th>Episodes</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
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
                        <td>{{ $program->media_contents_count }} <small class="muted">· {{ $program->episodes_count }} live</small></td>
                        <td>
                            @if($program->is_published)
                                <span class="badge green">Published</span>
                            @else
                                <span class="badge slate">Draft</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.programs.edit', $program) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.programs.destroy', $program) }}" onsubmit="return confirm('Delete this podcast? Its episodes stay in Media Content but are no longer grouped under it.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">{{ $activeType ? 'No podcasts in '.$activeType->name.' yet.' : 'No podcasts yet.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $programs->links() }}</div>
@endsection
