@extends('cms-admin::layout')

@section('title', 'Artists')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Artists</h1>
            <p>Artist profiles — bio, photo, socials, and verification status.</p>
        </div>
        <a href="{{ route('admin.artists.create') }}" class="primary-button"><i class="fa fa-plus"></i> Add Artist</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Verified</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($artists as $artist)
                    <tr>
                        <td>
                            <div class="table-name-cell">
                                @if($artist->photo)
                                    <img class="table-avatar" src="{{ $artist->photo_url }}" alt="">
                                @else
                                    <span class="table-avatar-fallback">{{ strtoupper(substr($artist->stage_name ?: $artist->name, 0, 2)) }}</span>
                                @endif
                                <div>
                                    <b>{{ $artist->stage_name ?: $artist->name }}</b>
                                    @if($artist->stage_name)<br><small class="muted">{{ $artist->name }}</small>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($artist->verified)
                                <span class="badge green"><i class="fa fa-check"></i> Verified</span>
                            @else
                                <span class="badge slate">Unverified</span>
                            @endif
                        </td>
                        <td>
                            @if($artist->status === 'active')
                                <span class="badge green">active</span>
                            @else
                                <span class="badge slate">inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('admin.artists.edit', $artist) }}" class="secondary-button compact">Edit</a>
                                <form method="POST" action="{{ route('admin.artists.destroy', $artist) }}" onsubmit="return confirm('Delete this artist?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button compact">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="4">No artists yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $artists->links() }}</div>
@endsection
