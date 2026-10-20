@extends('cms-admin::layout')

@section('title', 'Register User')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('Register User') }}</h1>
            <p>{{ __('Create a new account with access to the admin panel.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="panel form-card">
        @include('user-management::users._form', ['submitLabel' => __('Register')])
    </form>
@endsection
