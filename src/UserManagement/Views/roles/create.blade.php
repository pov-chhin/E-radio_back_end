@extends('cms-admin::layout')

@section('title', 'New Role')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('New Role') }}</h1>
            <p>{{ __('Define a role and pick the permissions it grants.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles.store') }}" class="panel form-card">
        @include('user-management::roles._form')
    </form>
@endsection
