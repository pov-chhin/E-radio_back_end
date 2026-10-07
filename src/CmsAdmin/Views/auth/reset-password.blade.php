@extends('cms-admin::auth.layout')

@section('title', __('Reset Password'))
@section('headline', __('Choose a new password.'))
@section('blurb', __('This link is only valid for 60 minutes and can only be used once.'))

@section('content')
    <h1 class="auth-title">{{ __('Reset your password') }}</h1>
    <p class="auth-sub">{{ __('Choose a new password for your account.') }}</p>

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <svg class="auth-icon"><use href="#i-alert"/></svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.password.update') }}" class="auth-form" data-auth-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label class="auth-label" for="email">{{ __('Email') }}</label>
            <div class="auth-field">
                <svg class="auth-icon"><use href="#i-mail"/></svg>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus
                       autocomplete="email" class="auth-input">
            </div>
        </div>

        <div>
            <label class="auth-label" for="password">{{ __('New password') }}</label>
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
            <label class="auth-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
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
            <span>{{ __('Reset password') }}</span>
            <svg class="auth-icon"><use href="#i-arrow"/></svg>
        </button>
    </form>
@endsection
