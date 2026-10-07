@extends('cms-admin::layout')

@section('title', 'New Category')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Catalog') }}</p>
            <h1>{{ __('New Category') }}</h1>
            <p>{{ __('Add a top-level section for listeners to browse.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="panel form-card">
        @include('cms-admin::categories._form')
    </form>
@endsection
