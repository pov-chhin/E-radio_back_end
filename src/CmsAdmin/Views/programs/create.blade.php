@extends('cms-admin::layout')

@section('title', 'New Program Podcast')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Content') }}</p>
            <h1>{{ __('New Program Podcast') }}</h1>
            <p>{{ __('Create the show first — you add its episodes on the next page.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.programs.store') }}" class="editor-form" enctype="multipart/form-data">
        @include('cms-admin::programs._form')
    </form>
@endsection
