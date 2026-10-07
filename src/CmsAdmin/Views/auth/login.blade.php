<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Log In') }} · {{ config('app.name') }}</title>
    @include('cms-admin::partials.locale-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white">
<div class="min-h-screen grid lg:grid-cols-2">
    {{-- Brand panel --}}
    <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-900 text-white p-12">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-indigo-500/20 blur-3xl"></div>

        <a href="/" class="relative z-10 inline-flex items-center gap-2 text-lg font-bold tracking-tight">
            <span class="inline-flex items-end gap-0.5 h-6">
                <span class="w-1 h-2.5 bg-white rounded-full"></span>
                <span class="w-1 h-5 bg-white rounded-full"></span>
                <span class="w-1 h-6 bg-white rounded-full"></span>
                <span class="w-1 h-3.5 bg-white rounded-full"></span>
            </span>
            AMS E-Radio
        </a>

        <div class="relative z-10">
            <h2 class="text-4xl font-bold leading-tight tracking-tight max-w-md">
                {{ __('Stream live radio, podcasts, and music from anywhere.') }}
            </h2>
            <p class="mt-4 text-indigo-200 max-w-sm text-sm">
                {{ __('Follow your favorite Cambodian stations, save what you love, and pick up right where you left off.') }}
            </p>
        </div>

        <p class="relative z-10 text-xs text-indigo-300">&copy; {{ __(':year AMS E-Radio. All rights reserved.', ['year' => date('Y')]) }}</p>
    </div>

    {{-- Form panel --}}
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="lg:hidden mb-8 inline-flex items-center gap-2 text-lg font-bold tracking-tight text-slate-900">
                <span class="inline-flex items-end gap-0.5 h-6">
                    <span class="w-1 h-2.5 bg-indigo-600 rounded-full"></span>
                    <span class="w-1 h-5 bg-indigo-600 rounded-full"></span>
                    <span class="w-1 h-6 bg-indigo-600 rounded-full"></span>
                    <span class="w-1 h-3.5 bg-indigo-600 rounded-full"></span>
                </span>
                AMS E-Radio
            </div>

            <h1 class="text-2xl font-bold text-slate-900">{{ __('Welcome back') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Log in to continue to :app.', ['app' => config('app.name')]) }}</p>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="identity">{{ __('Email or username') }}</label>
                    <input id="identity" name="identity" type="text" value="{{ old('identity') }}" required autofocus
                           placeholder="{{ __('you@example.com or yourusername') }}"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-medium text-slate-700" for="password">{{ __('Password') }}</label>
                        <a href="{{ route('customer.password.request') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                            {{ __('Forgot username or password?') }}
                        </a>
                    </div>
                    <input id="password" name="password" type="password" required
                           placeholder="••••••••"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    {{ __('Remember me') }}
                </label>
                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 text-white text-sm font-semibold py-2.5 shadow-sm hover:bg-indigo-500 transition-colors">
                    {{ __('Log in') }}
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-8">
                {{ __("Don't have an account?") }}
                <a href="{{ route('customer.register') }}" class="font-semibold text-indigo-600 hover:text-indigo-500">{{ __('Sign up') }}</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
