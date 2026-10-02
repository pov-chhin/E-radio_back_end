@extends('cms-admin::layout')

@section('title', 'Edit Media Content')

@section('content')
    <form method="POST" action="{{ route('admin.media-contents.update', $mediaContent) }}" class="editor-form" enctype="multipart/form-data" data-media-upload-form>
        @method('PUT')
        @include('cms-admin::media-contents._form')
    </form>
@endsection
