@extends('cms-admin::layout')

@section('title', 'Register User')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Team') }}</p>
            <h1>{{ __('Register User') }}</h1>
            <p>{{ __('Create a new account with access to the admin panel.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="panel form-card">
        @csrf

        <div class="form-grid" style="margin-bottom:24px">
            <label>{{ __('Name') }}
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                @error('name') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Email') }}
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                @error('email') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Password') }}
                <input id="password" name="password" type="password" required>
                @error('password') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Language') }}
                <select id="locale" name="locale">
                    <option value="">{{ __('Site default') }}</option>
                    @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                        <option value="{{ $code }}" @selected(old('locale') === $code)>{{ $language['name'] }} ({{ $language['short'] }})</option>
                    @endforeach
                </select>
                @error('locale') <em>{{ $message }}</em> @enderror
            </label>
        </div>

        <div style="margin-bottom:24px">
            <label style="display:block;font-size:9px;font-weight:700;margin-bottom:10px">{{ __('Roles') }}</label>
            <div class="checkbox-grid">
                @forelse($roles as $role)
                    <label>
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(collect(old('roles', []))->contains($role->id))>
                        {{ __($role->name) }}
                    </label>
                @empty
                    <p class="muted">{!! __('No roles yet — <a href=":url">create one</a>.', ['url' => route('admin.roles.create')]) !!}</p>
                @endforelse
            </div>
        </div>

        <button type="submit" class="primary-button">{{ __('Register') }}</button>
    </form>
@endsection
