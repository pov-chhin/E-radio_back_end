@extends('cms-admin::auth.layout')

@section('title', __('Create Account'))
@section('headline', __('Join a community of listeners across Cambodia.'))
@section('blurb', __('Create a free account to save favorites, follow stations, and get picks tailored to you.'))

@section('content')
    <h1 class="auth-title">{{ __('Create your account') }}</h1>
    <p class="auth-sub">{{ __("Join :app — it's free.", ['app' => config('app.name')]) }}</p>

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <svg class="auth-icon"><use href="#i-alert"/></svg>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.register.store') }}" class="auth-form" data-auth-form>
        @csrf
        <div class="auth-row-2">
            <div>
                <label class="auth-label" for="first_name">{{ __('First name') }}</label>
                <div class="auth-field">
                    <svg class="auth-icon"><use href="#i-user"/></svg>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required autofocus
                           autocomplete="given-name" placeholder="{{ __('First name') }}" class="auth-input">
                </div>
            </div>
            <div>
                <label class="auth-label" for="last_name">{{ __('Last name') }}</label>
                <div class="auth-field">
                    <svg class="auth-icon"><use href="#i-user"/></svg>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}"
                           autocomplete="family-name" placeholder="{{ __('Last name') }}" class="auth-input">
                </div>
            </div>
        </div>

        <div>
            <label class="auth-label" for="username">{{ __('Username') }}</label>
            <div class="auth-field">
                <svg class="auth-icon"><use href="#i-at"/></svg>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       placeholder="{{ __('yourusername') }}" class="auth-input">
            </div>
        </div>

        <div>
            <label class="auth-label" for="email">{{ __('Email') }}</label>
            <div class="auth-field">
                <svg class="auth-icon"><use href="#i-mail"/></svg>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       autocomplete="email" placeholder="you@example.com" class="auth-input">
            </div>
            <p class="auth-hint">{{ __('Used only for account recovery.') }}</p>
        </div>

        <div>
            <label class="auth-label" for="password">{{ __('Password') }}</label>
            <div class="auth-field has-toggle">
                <svg class="auth-icon"><use href="#i-lock"/></svg>
                <input id="password" name="password" type="password" required
                       autocomplete="new-password" placeholder="••••••••" class="auth-input">
                <button type="button" class="auth-eye" data-toggle-password="password" aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="auth-icon"><use href="#i-eye"/></svg>
                </button>
            </div>
        </div>

        <div>
            <label class="auth-label" for="password_confirmation">{{ __('Confirm password') }}</label>
            <div class="auth-field has-toggle">
                <svg class="auth-icon"><use href="#i-lock"/></svg>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       autocomplete="new-password" placeholder="••••••••" class="auth-input">
                <button type="button" class="auth-eye" data-toggle-password="password_confirmation" aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="auth-icon"><use href="#i-eye"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-btn">
            <span>{{ __('Create account') }}</span>
            <svg class="auth-icon"><use href="#i-arrow"/></svg>
        </button>
    </form>

    <p class="auth-alt">
        {{ __('Have an account?') }}
        <a href="{{ route('login') }}">{{ __('Log in') }}</a>
    </p>
@endsection
