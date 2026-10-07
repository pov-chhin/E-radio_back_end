@extends('cms-admin::auth.layout')

@section('title', __('Forgot Username or Password'))
@section('headline', __("We'll help you get back in."))
@section('blurb', __("Enter the email on your account and we'll send your username along with a link to reset your password."))

@section('content')
    <h1 class="auth-title">{{ __('Forgot username or password?') }}</h1>
    <p class="auth-sub">{{ __("Enter your account email and we'll send recovery instructions.") }}</p>

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

    <form method="POST" action="{{ route('customer.password.email') }}" class="auth-form" data-auth-form>
        @csrf
        <div>
            <label class="auth-label" for="email">{{ __('Email') }}</label>
            <div class="auth-field">
                <svg class="auth-icon"><use href="#i-mail"/></svg>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="email" placeholder="you@example.com" class="auth-input">
            </div>
        </div>

        <button type="submit" class="auth-btn">
            <span>{{ __('Send recovery instructions') }}</span>
            <svg class="auth-icon"><use href="#i-arrow"/></svg>
        </button>
    </form>

    <p class="auth-alt">
        <a href="{{ route('login') }}">{{ __('Back to log in') }}</a>
    </p>
@endsection
