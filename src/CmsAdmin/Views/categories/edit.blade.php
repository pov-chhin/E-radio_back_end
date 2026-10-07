@extends('cms-admin::layout')

@section('title', 'Edit Category')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Edit Category') }}</h1>
            <p>{{ __('Update :name.', ['name' => $category->name]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="panel form-card">
        @method('PUT')
        @include('cms-admin::categories._form')
    </form>
@endsection
