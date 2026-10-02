@extends('cms-admin::layout')

@section('title', 'Categories')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Categories</h1>
            <p>The top-level sections listeners browse by — Podcast, News, Song, and so on.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="primary-button">New category</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Podcasts</th>
                    <th>Media content</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td><b>{{ $category->name }}</b></td>
                        <td class="muted">{{ $category->slug }}</td>
                        <td>{{ $category->programs_count }}</td>
                        <td>{{ $category->media_contents_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Its podcasts and media content will be deleted too.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
@endsection
