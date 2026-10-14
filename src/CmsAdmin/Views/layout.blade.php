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
                <div class="dropdown-anchor">
                    <button class="user-menu-toggle" aria-label="{{ __('Account menu') }}" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
                        <span class="user-avatar">@if($admin?->photo_url)<img src="{{ $admin->photo_url }}" alt="">@else{{ strtoupper(substr($admin?->name ?? 'A', 0, 2)) }}@endif</span>
                        <span class="user-menu-name">{{ $admin?->name }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-right account-menu" data-dropdown>
                        <div class="account-menu-head">
                            <span class="account-menu-avatar" aria-hidden="true">@if($admin?->photo_url)<img src="{{ $admin->photo_url }}" alt="">@else{{ strtoupper(mb_substr($admin?->name ?? 'A', 0, 2)) }}@endif</span>
                            <div class="account-menu-who">
                                <b>{{ $admin?->name }}</b>
                                <small>{{ $admin?->email }}</small>
                                @if($adminRole = $admin?->roles->first())
                                    <span class="badge slate">{{ __($adminRole->name) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="account-menu-body">
                            <a href="{{ route('admin.profile.show') }}" class="account-menu-item"><i class="fa fa-user-o"></i>{{ __('My Profile') }}</a>

                            <p class="dropdown-label">{{ __('Language') }}</p>
                            <div class="account-menu-langs" role="group" aria-label="{{ __('Language') }}">
                                @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                                    <form method="POST" action="{{ route('admin.locale.update') }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="locale" value="{{ $code }}">
                                        <button type="submit" class="account-menu-lang{{ app()->getLocale() === $code ? ' is-active' : '' }}" @if(app()->getLocale() === $code) aria-current="true" @endif>
                                            @if(app()->getLocale() === $code)<i class="fa fa-check"></i>@endif
                                            <span>{{ $language['name'] }}</span> <small>{{ $language['short'] }}</small>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>

                        <div class="account-menu-foot">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="account-menu-item account-menu-logout"><i class="fa fa-sign-out"></i>{{ __('Log out') }}</button>
                            </form>
                        </div>
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
<script>
    // "Retry" on a failed audio fetch or conversion is sent from the page itself, so nothing reloads: the row's
    // "Failed" badge turns into "Processing" where it stands. If the request can't be made this way (a session that
    // ran out, no JSON back), the form is simply submitted the ordinary way.
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-retry-audio]')) return;
        event.preventDefault();

        const button = event.submitter || (form.id && document.querySelector('[form="' + form.id + '"]')) || form.querySelector('button');
        if (button?.disabled) return;
        if (button) button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = '<span><i class="fa fa-check-circle"></i></span>';
            toast.append(data.message || '');
            document.querySelector('.page-wrap')?.before(toast);
            setTimeout(() => toast.remove(), 6000);

            if (!data.started) {
                if (button) button.disabled = false;
                return;
            }

            const holder = button?.closest('td, label') || form.closest('td');
            const badge = holder?.querySelector('[data-retry-badge]');
            if (badge) {
                badge.className = 'badge amber';
                badge.textContent = form.dataset.processingLabel || '';
                badge.removeAttribute('title');
            }
            holder?.querySelector('[data-retry-reason]')?.remove();
            if (form.closest('td')) form.remove(); else button?.remove();
        } catch (error) {
            form.submit();
        }
    });
</script>
</body>
</html>
