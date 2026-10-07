{{-- CmsAdmin module layout --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __($__env->yieldContent('title') ?: 'Admin') }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    @include('cms-admin::partials.locale-head')
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
            <button class="mobile-menu" data-menu-toggle aria-label="{{ __('Open menu') }}"><i class="fa fa-bars"></i></button>
            <div class="breadcrumbs"><span>{{ __('Admin panel') }}</span><b>/</b><strong>{{ __($__env->yieldContent('title') ?: 'Overview') }}</strong></div>
            <div class="top-actions">
                @php
                    $latestNotifications = $admin?->notifications()->latest()->take(6)->get();
                    $unreadNotificationCount = $admin?->unreadNotifications()->count() ?? 0;
                @endphp
                <div class="dropdown-anchor">
                    <button class="icon-button notification" aria-label="{{ __('Notifications') }}" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle><span class="fa fa-bell bell-icon"></span>@if($unreadNotificationCount > 0)<i></i>@endif</button>
                    <div class="dropdown-menu dropdown-right notification-panel" data-dropdown>
                        <p class="dropdown-label">{{ __('Notifications') }}</p>
                        @forelse($latestNotifications ?? [] as $notification)
                            <div class="notif-row {{ $notification->read_at ? '' : 'unread' }}">
                                <span class="activity-dot {{ $notification->data['tone'] ?? '' }}"></span>
                                <div><b>{{ __($notification->data['title'] ?? 'Notification') }}</b><small>{{ $notification->data['meta'] ?? '' }} · {{ $notification->created_at->diffForHumans() }}</small></div>
                            </div>
                        @empty
                            <p class="dropdown-meta">{{ __('No notifications yet.') }}</p>
                        @endforelse
                        <div class="dropdown-divider"></div>
                        @if($unreadNotificationCount > 0)
                            <form method="POST" action="{{ route('admin.notifications.readAll') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">{{ __('Mark all read') }}</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.notifications.index') }}">{{ __('View all notifications') }}</a>
                    </div>
                </div>
                <a class="secondary-button compact" href="{{ url('/') }}" target="_blank" rel="noopener">{{ __('View site') }}</a>
                <div class="dropdown-anchor">
                    <button class="user-menu-toggle" aria-label="{{ __('Account menu') }}" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
                        <span class="user-avatar">{{ strtoupper(substr($admin?->name ?? 'A', 0, 2)) }}</span>
                        <span class="user-menu-name">{{ $admin?->name }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-right" data-dropdown>
                        <div class="user-menu-info"><b>{{ $admin?->name }}</b><small>{{ $admin?->email }}</small></div>
                        <div class="dropdown-divider"></div>
                        <p class="dropdown-label">{{ __('Language') }}</p>
                        @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                            <form method="POST" action="{{ route('admin.locale.update') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="locale" value="{{ $code }}">
                                <button type="submit" class="dropdown-item" @if(app()->getLocale() === $code) aria-current="true" style="font-weight:700" @endif>{{ $language['name'] }} <small>{{ $language['short'] }}</small>@if(app()->getLocale() === $code) <i class="fa fa-check"></i>@endif</button>
                            </form>
                        @endforeach
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">{{ __('Log out') }}</button>
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
