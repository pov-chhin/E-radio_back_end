@extends('cms-admin::layout')

@section('title', 'Edit Role')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('Edit Role') }}</h1>
            <p>{{ __("Update :name's name, slug, and permissions.", ['name' => __($role->name)]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="panel form-card">
        @method('PUT')
        @include('user-management::roles._form')
    </form>
@endsection
