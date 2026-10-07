@extends('cms-admin::layout')

@section('title', 'New Artist')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Taxonomy') }}</p>
            <h1>{{ __('New Artist') }}</h1>
            <p>{{ __('Add an artist profile.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.artists.store') }}" class="panel form-card" enctype="multipart/form-data">
        @include('cms-admin::artists._form')
    </form>
@endsection
