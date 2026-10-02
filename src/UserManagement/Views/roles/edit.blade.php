@extends('cms-admin::layout')

@section('title', 'Edit Role')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Access control</p>
            <h1>Edit Role</h1>
            <p>Update {{ $role->name }}'s name, slug, and permissions.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="panel form-card">
        @method('PUT')
        @include('user-management::roles._form')
    </form>
@endsection
