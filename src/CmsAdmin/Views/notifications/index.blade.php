@extends('cms-admin::layout')

@section('title', 'Notifications')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Updates</p>
            <h1>Notifications</h1>
            <p>Everything that's happened across your stations and admin panel.</p>
        </div>
        @if($hasUnread)
            <form method="POST" action="{{ route('admin.notifications.readAll') }}">
                @csrf
                <button type="submit" class="secondary-button">Mark all read</button>
            </form>
        @endif
    </div>

    <section class="panel activity-panel">
        <div class="activity-list">
            @forelse($notifications as $notification)
                <div class="activity-item">
                    <span class="activity-dot {{ $notification->data['tone'] ?? '' }}"></span>
                    <div>
                        <b>{{ $notification->data['title'] ?? 'Notification' }}</b>
                        <small>{{ $notification->data['meta'] ?? '' }} · {{ $notification->created_at->diffForHumans() }}{{ $notification->read_at ? '' : ' · Unread' }}</small>
                    </div>
                    <div class="table-actions" style="margin-left:auto">
                        @if(!$notification->read_at)
                            <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="secondary-button compact">Mark read</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}" onsubmit="return confirm('Delete this notification?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger-button compact">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="empty-state">No notifications yet.</p>
            @endforelse
        </div>
    </section>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
