@extends('cms-admin::layout')

@section('title', 'Notifications')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Updates') }}</p>
            <h1>{{ __('Notifications') }}</h1>
            <p>{{ __('Everything that\'s happened across your stations and admin panel.') }}</p>
        </div>
        @if($hasUnread)
            <form method="POST" action="{{ route('admin.notifications.readAll') }}">
                @csrf
                <button type="submit" class="secondary-button">{{ __('Mark all read') }}</button>
            </form>
        @endif
    </div>

    <section class="panel activity-panel">
        <div class="activity-list">
            @forelse($notifications as $notification)
                <div class="activity-item">
                    <span class="activity-dot {{ $notification->data['tone'] ?? '' }}"></span>
                    <div>
                        <b>{{ __($notification->data['title'] ?? 'Notification') }}</b>
                        <small>{{ __($notification->data['meta'] ?? '') }} · {{ $notification->created_at->diffForHumans() }}{{ $notification->read_at ? '' : ' · '.__('Unread') }}</small>
                    </div>
                    <div class="table-actions" style="margin-left:auto">
                        @if(!$notification->read_at)
                            <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="secondary-button compact">{{ __('Mark read') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}" onsubmit="return confirm(@js(__('Delete this notification?')))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger-button compact">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="empty-state">{{ __('No notifications yet.') }}</p>
            @endforelse
        </div>
    </section>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
