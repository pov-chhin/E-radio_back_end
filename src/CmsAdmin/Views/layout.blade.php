{{-- CmsAdmin module layout --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-app">
<div class="app-shell">
    @php
        $admin = auth('admin')->user();
    @endphp
    @include('cms-admin::partials.sidebar', ['admin' => $admin])

    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" data-menu-toggle aria-label="Open menu"><i class="fa fa-bars"></i></button>
            <div class="breadcrumbs"><span>Admin</span><b>/</b><strong>@yield('title', 'Overview')</strong></div>
            <div class="top-actions">
                @php
                    $latestNotifications = $admin?->notifications()->latest()->take(6)->get();
                    $unreadNotificationCount = $admin?->unreadNotifications()->count() ?? 0;
                @endphp
                <div class="dropdown-anchor">
                    <button class="icon-button notification" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle><span class="fa fa-bell bell-icon"></span>@if($unreadNotificationCount > 0)<i></i>@endif</button>
                    <div class="dropdown-menu dropdown-right notification-panel" data-dropdown>
                        <p class="dropdown-label">Notifications</p>
                        @forelse($latestNotifications ?? [] as $notification)
                            <div class="notif-row {{ $notification->read_at ? '' : 'unread' }}">
                                <span class="activity-dot {{ $notification->data['tone'] ?? '' }}"></span>
                                <div><b>{{ $notification->data['title'] ?? 'Notification' }}</b><small>{{ $notification->data['meta'] ?? '' }} · {{ $notification->created_at->diffForHumans() }}</small></div>
                            </div>
                        @empty
                            <p class="dropdown-meta">No notifications yet.</p>
                        @endforelse
                        <div class="dropdown-divider"></div>
                        @if($unreadNotificationCount > 0)
                            <form method="POST" action="{{ route('admin.notifications.readAll') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Mark all read</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.notifications.index') }}">View all notifications</a>
                    </div>
                </div>
                <a class="secondary-button compact" href="{{ url('/') }}" target="_blank" rel="noopener">View site</a>
                <div class="dropdown-anchor">
                    <button class="user-menu-toggle" aria-label="Account menu" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
                        <span class="user-avatar">{{ strtoupper(substr($admin?->name ?? 'A', 0, 2)) }}</span>
                        <span class="user-menu-name">{{ $admin?->name }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-right" data-dropdown>
                        <div class="user-menu-info"><b>{{ $admin?->name }}</b><small>{{ $admin?->email }}</small></div>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        @if(session('status'))<div class="toast" data-toast><span><i class="fa fa-check-circle"></i></span>{{ session('status') }}<button><i class="fa fa-times"></i></button></div>@endif
        @if(session('error'))<div class="inline-warning danger">{{ session('error') }}</div>@endif

        <div class="page-wrap">
            @yield('content')
        </div>
    </main>
</div>
<audio data-preview-audio preload="none"></audio>
</body>
</html>
