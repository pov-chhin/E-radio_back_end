<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reset Password') }} · {{ config('app.name') }}</title>
    @include('cms-admin::partials.locale-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white">
<div class="min-h-screen grid lg:grid-cols-2">
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
                {{ __('Choose a new password.') }}
            </h2>
            <p class="mt-4 text-indigo-200 max-w-sm text-sm">
                {{ __('This link is only valid for 60 minutes and can only be used once.') }}
            </p>
        </div>

        <p class="relative z-10 text-xs text-indigo-300">&copy; {{ __(':year AMS E-Radio. All rights reserved.', ['year' => date('Y')]) }}</p>
    </div>

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

            <h1 class="text-2xl font-bold text-slate-900">{{ __('Reset your password') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Choose a new password for your account.') }}</p>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.password.update') }}" class="mt-8 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="email">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password">{{ __('New password') }}</label>
                    <input id="password" name="password" type="password" required
                           placeholder="••••••••"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password_confirmation">{{ __('Confirm new password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           placeholder="••••••••"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 text-white text-sm font-semibold py-2.5 shadow-sm hover:bg-indigo-500 transition-colors">
                    {{ __('Reset password') }}
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
