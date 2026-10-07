{{-- RadioStream module layout --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __($__env->yieldContent('title') ?: 'Overview') }} · Airwave</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    @include('cms-admin::partials.locale-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="radio-app" data-stream-url="{{ $station['stream_url'] }}">
<div class="app-shell">
    @php
        $admin = auth('admin')->user();
        $nav = [
            ['index','Overview','th-large'], ['live','Live Studio','signal'], ['stations','Stations','server'],
            ['player','Web Player','play'], ['playlist','Music Library','music'], ['schedule','Schedule','calendar'],
            ['listeners','Listeners','users'], ['settings','Settings','cog'],
        ];
    @endphp
    @include('cms-admin::partials.sidebar', ['admin' => $admin])

    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" data-menu-toggle aria-label="{{ __('Open menu') }}"><i class="fa fa-bars"></i></button>
            <div class="breadcrumbs"><span>Airwave</span><b>/</b><strong>{{ __($__env->yieldContent('title') ?: 'Overview') }}</strong></div>
            <div class="top-actions">
                @php
                    $notificationUser = auth('admin')->user();
                    $latestNotifications = $notificationUser->notifications()->latest()->take(6)->get();
                    $unreadNotificationCount = $notificationUser->unreadNotifications()->count();

                    $navPayload = collect($nav)->map(fn ($item) => [
                        'route' => $item[0], 'label' => __($item[1]), 'icon' => $item[2],
                        'href' => route('dashboard.'.$item[0]),
                    ])->values();

                    $notificationsPayload = $latestNotifications->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? __('Notification'),
                        'meta' => $notification->data['meta'] ?? '',
                        'tone' => $notification->data['tone'] ?? '',
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at->diffForHumans(),
                    ])->values();
                @endphp
                <div
                    id="topbar-menus"
                    data-nav="{{ $navPayload->toJson() }}"
                    data-notifications="{{ $notificationsPayload->toJson() }}"
                    data-unread-count="{{ $unreadNotificationCount }}"
                    data-mark-all-read-url="{{ route('admin.notifications.readAll') }}"
                    data-notifications-index-url="{{ route('admin.notifications.index') }}"
                ></div>
                <a class="primary-button compact" href="{{ route('dashboard.live') }}"><span class="live-pulse"></span>{{ __('Go live') }}</a>
            </div>
        </header>

        @if(session('status'))<div class="toast" data-toast><span><i class="fa fa-check-circle"></i></span>{{ session('status') }}<button><i class="fa fa-times"></i></button></div>@endif
        @if(session()->has('radio.active_station'))<div class="inline-warning">{{ __('Viewing') }} <b>{{ $station['name'] }}</b> · <a class="text-button" href="{{ route('dashboard.station.reset') }}">{{ __('Back to all stations') }}</a></div>@endif
        @if(!empty($integration_warning))<div class="inline-warning">{{ __($integration_warning) }}</div>@endif
        @if(!empty($protected_data_warning) && request()->routeIs('dashboard.live', 'dashboard.playlist', 'dashboard.listeners'))<div class="inline-warning">{{ __($protected_data_warning) }}</div>@endif

        <div class="page-wrap">
            @yield('content')
        </div>
    </main>
</div>

<div class="global-player" data-player>
    <div class="player-track">
        <div class="album-art">@if($station['art'])<img src="{{ $station['art'] }}" alt="">@else<span>AW</span>@endif<div class="art-glow"></div></div>
        <div class="track-copy"><b data-track-title>{{ __($station['now_playing']) }}</b><span>{{ __($station['artist']) }} · {{ __($station['name']) }}</span></div>
        <span class="on-air-tag"><i></i> {{ $station['is_online'] ? __('ON AIR') : __('OFFLINE') }}</span>
    </div>
    <div class="player-controls">
        <button class="skip-button" aria-label="{{ __('Previous') }}"><i class="fa fa-step-backward"></i></button>
        <button class="play-button" data-play-toggle aria-label="{{ __('Play stream') }}"><span class="play-triangle"></span></button>
        <button class="skip-button" aria-label="{{ __('Next') }}"><i class="fa fa-step-forward"></i></button>
    </div>
    <div class="player-volume">
        <i class="fa fa-volume-up volume-icon"></i><input data-volume type="range" min="0" max="100" value="72" aria-label="{{ __('Volume') }}"><span>HD</span>
    </div>
    <audio data-audio preload="none"></audio>
</div>
</body>
</html>
