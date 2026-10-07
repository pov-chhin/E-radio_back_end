<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @include('cms-admin::partials.locale-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">

{{-- Icon sprite: <svg class="auth-icon"><use href="#i-mail"/></svg> --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3.5 7.5 8.5 6 8.5-6"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c.6-3.6 3.8-5.5 7.5-5.5s6.9 1.9 7.5 5.5"/></symbol>
        <symbol id="i-at" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.6 5.6A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3.2 3.9M6.6 6.9A15.6 15.6 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4.4-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.1"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.4 2.8 2.8L16.2 9.6"/></symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
        <symbol id="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.6v12.8a1 1 0 0 0 1.5.86l10.4-6.4a1 1 0 0 0 0-1.72L9.5 4.74A1 1 0 0 0 8 5.6Z"/></symbol>
    </defs>
</svg>

<div class="auth-shell">

    {{-- Brand panel (desktop) --}}
    <aside class="auth-hero">
        <div class="auth-hero-fx" aria-hidden="true">
            <i class="auth-ring auth-ring-1"></i>
            <i class="auth-ring auth-ring-2"></i>
            <i class="auth-ring auth-ring-3"></i>
        </div>

        <a href="/" class="auth-brand">
            <span class="auth-logo" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            <span>AMS E-Radio</span>
        </a>

        <div class="auth-hero-body">
            <h2>@yield('headline')</h2>
            <p>@yield('blurb')</p>

            <div class="auth-player" aria-hidden="true">
                <span class="auth-player-art"><i></i><i></i><i></i><i></i></span>
                <span class="auth-player-meta">
                    <em class="auth-onair"><i></i>{{ __('On air') }}</em>
                    <b>AMS E-Radio</b>
                    <small>{{ __('Streaming live') }}</small>
                </span>
                <span class="auth-player-play"><svg class="auth-icon"><use href="#i-play"/></svg></span>
                <span class="auth-player-track"><i></i></span>
            </div>

            <ul class="auth-chips">
                <li>{{ __('Live radio') }}</li>
                <li>{{ __('Podcasts') }}</li>
                <li>{{ __('Ambient Music') }}</li>
            </ul>
        </div>

        <div class="auth-eq" aria-hidden="true">
            @php
                $eqDur = [1.15, 0.82, 1.4, 0.95, 1.25, 0.72, 1.05];
            @endphp
            @for ($i = 0; $i < 36; $i++)
                <i style="--d: {{ $eqDur[$i % 7] }}s; --o: {{ number_format(-0.11 * $i, 2) }}s; --h: {{ number_format(0.35 + (($i * 37) % 60) / 100, 2) }}"></i>
            @endfor
        </div>

        <p class="auth-copy">&copy; {{ __(':year AMS E-Radio. All rights reserved.', ['year' => date('Y')]) }}</p>
    </aside>

    {{-- Form panel --}}
    <main class="auth-main">
        {{-- Brand banner (mobile / tablet) --}}
        <div class="auth-banner">
            <a href="/" class="auth-brand">
                <span class="auth-logo" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <span>AMS E-Radio</span>
            </a>
        </div>

        <div class="auth-card">
            @yield('content')
        </div>

        <p class="auth-copy-mobile">&copy; {{ __(':year AMS E-Radio. All rights reserved.', ['year' => date('Y')]) }}</p>
    </main>
</div>

<script>
    // Show / hide password
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var use = btn.querySelector('use');
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
            if (use) use.setAttribute('href', show ? '#i-eye-off' : '#i-eye');
            input.focus();
        });
    });

    // Disable the submit button while the request is in flight so it cannot be double-sent.
    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[type=submit]');
            if (!btn) return;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            setTimeout(function () { btn.disabled = true; }, 0);
        });
    });
    // Coming back with the browser's back button restores the page from cache with the button still locked.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-auth-form] [type=submit]').forEach(function (btn) {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
        });
    });
</script>
</body>
</html>
