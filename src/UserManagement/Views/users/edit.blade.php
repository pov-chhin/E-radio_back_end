@extends('cms-admin::layout')

@section('title', 'Edit User')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Team') }}</p>
            <h1>{{ __('Edit User') }}</h1>
            <p>{{ __("Update :name's account, language, and roles.", ['name' => $user->name]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="panel form-card">
        @method('PUT')
        @include('user-management::users._form', ['submitLabel' => __('Save changes')])
    </form>
@endsection
