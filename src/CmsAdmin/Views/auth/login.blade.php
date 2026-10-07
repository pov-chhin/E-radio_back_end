@extends('cms-admin::auth.layout')

@section('title', __('Log In'))
@section('headline', __('Stream live radio, podcasts, and music from anywhere.'))
@section('blurb', __('Follow your favorite Cambodian stations, save what you love, and pick up right where you left off.'))

@section('content')
    <h1 class="auth-title">{{ __('Welcome back') }}</h1>
    <p class="auth-sub">{{ __('Log in to continue to :app.', ['app' => config('app.name')]) }}</p>

    @if (session('status'))
        <div class="auth-alert auth-alert-success" role="status">
            <svg class="auth-icon"><use href="#i-check"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <svg class="auth-icon"><use href="#i-alert"/></svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" class="auth-form" data-auth-form>
        @csrf
        <div>
            <label class="auth-label" for="identity">{{ __('Email or username') }}</label>
            <div class="auth-field">
                <svg class="auth-icon"><use href="#i-user"/></svg>
                <input id="identity" name="identity" type="text" value="{{ old('identity') }}" required autofocus
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       placeholder="{{ __('you@example.com or yourusername') }}"
                       class="auth-input">
            </div>
        </div>

        <div>
            <label class="auth-label" for="password">{{ __('Password') }}</label>
            <div class="auth-field has-toggle">
                <svg class="auth-icon"><use href="#i-lock"/></svg>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password"
                       placeholder="••••••••"
                       class="auth-input">
                <button type="button" class="auth-eye" data-toggle-password="password" aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="auth-icon"><use href="#i-eye"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-btn">
            <span>{{ __('Log in') }}</span>
            <svg class="auth-icon"><use href="#i-arrow"/></svg>
        </button>

        <div class="auth-row-between">
            <label class="auth-check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                {{ __('Remember me') }}
            </label>
            <a href="{{ route('customer.password.request') }}" class="auth-link">{{ __('Forgot username or password?') }}</a>
        </div>
    </form>
@endsection
