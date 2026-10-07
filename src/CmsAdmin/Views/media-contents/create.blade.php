@extends('cms-admin::layout')

{{-- "New Song Media Content" when the form starts on a category; the form's script keeps it in step with the Category select. --}}
@section('title', ($titleCategory ?? null) ? __('New :category Media Content', ['category' => $titleCategory->name]) : 'New Media Content')

@section('content')
    <form method="POST" action="{{ route('admin.media-contents.store') }}" class="editor-form" enctype="multipart/form-data" data-media-upload-form>
        @include('cms-admin::media-contents._form')
    </form>
@endsection
