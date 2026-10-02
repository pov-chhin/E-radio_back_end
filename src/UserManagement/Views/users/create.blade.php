@extends('cms-admin::layout')

@section('title', 'Register User')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Team</p>
            <h1>Register User</h1>
            <p>Create a new account with access to the admin panel.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="panel form-card">
        @csrf

        <div class="form-grid" style="margin-bottom:24px">
            <label>Name
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                @error('name') <em>{{ $message }}</em> @enderror
            </label>
            <label>Email
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                @error('email') <em>{{ $message }}</em> @enderror
            </label>
            <label>Password
                <input id="password" name="password" type="password" required>
                @error('password') <em>{{ $message }}</em> @enderror
            </label>
        </div>

        <div style="margin-bottom:24px">
            <label style="display:block;font-size:9px;font-weight:700;margin-bottom:10px">Roles</label>
            <div class="checkbox-grid">
                @forelse($roles as $role)
                    <label>
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(collect(old('roles', []))->contains($role->id))>
                        {{ $role->name }}
                    </label>
                @empty
                    <p class="muted">No roles yet — <a href="{{ route('admin.roles.create') }}">create one</a>.</p>
                @endforelse
            </div>
        </div>

        <button type="submit" class="primary-button">Register</button>
    </form>
@endsection
