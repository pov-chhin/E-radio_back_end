@extends('cms-admin::layout')

@section('title', 'Tags')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Tags</h1>
            <p>The nested "By Tag" tree used to filter and describe content — genres, topics, moods.</p>
        </div>
        <a href="{{ route('admin.tags.create') }}" class="primary-button">New tag</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tags as $tag)
                    <tr>
                        <td style="padding-left:{{ 18 + $tag->depth * 22 }}px">
                            @if($tag->depth > 0)<span class="muted">— </span>@endif<b>{{ $tag->name }}</b>
                        </td>
                        <td class="muted">{{ $categoryNames[$tag->category_id] ?? '—' }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.tags.edit', $tag->id) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.tags.destroy', $tag->id) }}" onsubmit="return confirm('Delete this tag? Any child tags will be deleted too.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="3">No tags yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
