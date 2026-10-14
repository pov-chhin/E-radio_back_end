@extends('cms-admin::layout')

{{-- "Edit Song Media Content" from the Article page, like its New button; the plain edit address keeps the plain title. --}}
@section('title', ($titleCategory ?? null) ? __('Edit :category Media Content', ['category' => $titleCategory->name]) : 'Edit Media Content')

@section('content')
    <form method="POST" action="{{ route('admin.media-contents.update', $mediaContent) }}" class="editor-form" enctype="multipart/form-data" data-media-upload-form>
        @method('PUT')
        @include('cms-admin::media-contents._form')
    </form>

    {{-- The Retry button in the form above belongs to this one (a form cannot sit inside another form). --}}
    @if(in_array($mediaContent->processing_status, ['processing', 'failed'], true))
        <form id="retry-audio-form" method="POST" action="{{ route('admin.media-contents.retry-audio', $mediaContent) }}" data-retry-audio hidden>@csrf</form>
    @endif
@endsection
