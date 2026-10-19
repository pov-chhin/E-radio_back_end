@extends('cms-admin::layout')

@section('title', 'Edit Tag')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('Edit Tag') }}</h1>
            <p>{{ __('Update :name.', ['name' => $tag->name]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.tags.update', $tag) }}" class="panel form-card" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::tags._form')
    </form>
@endsection
