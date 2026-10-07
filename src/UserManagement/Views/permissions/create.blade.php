@extends('cms-admin::layout')

@section('title', 'New Permission')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Access control') }}</p>
            <h1>{{ __('New Permission') }}</h1>
            <p>{{ __('Define a capability that roles can be granted.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.permissions.store') }}" class="panel form-card">
        @csrf

        <div class="form-grid" style="margin-bottom:24px">
            <label>{{ __('Name') }}
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                @error('name') <em>{{ $message }}</em> @enderror
            </label>
            <label>{{ __('Slug') }}
                <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required>
                @error('slug') <em>{{ $message }}</em> @enderror
            </label>
        </div>

        <button type="submit" class="primary-button">{{ __('Save') }}</button>
    </form>
@endsection
