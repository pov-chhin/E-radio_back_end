@extends('cms-admin::layout')

@section('title', 'New Media Content')

@section('content')
    <form method="POST" action="{{ route('admin.media-contents.store') }}" class="editor-form" enctype="multipart/form-data" data-media-upload-form>
        @include('cms-admin::media-contents._form')
    </form>
@endsection
