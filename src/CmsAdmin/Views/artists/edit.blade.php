@extends('cms-admin::layout')

@section('title', 'Edit Artist')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Catalog') }}</p>
            <h1>{{ __('Edit Artist') }}</h1>
            <p>{{ __('Update :name.', ['name' => $artist->name]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.artists.update', $artist) }}" class="panel form-card" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::artists._form')
    </form>
@endsection
