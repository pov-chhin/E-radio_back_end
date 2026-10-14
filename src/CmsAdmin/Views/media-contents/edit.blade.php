@extends('cms-admin::layout')

{{-- "Edit Song Media Content" from the Article page, like its New button; the plain edit address keeps the plain title. --}}
@section('title', ($titleCategory ?? null) ? __('Edit :category Media Content', ['category' => $titleCategory->name]) : 'Edit Media Content')

@section('content')
    <form method="POST" action="{{ route('admin.media-contents.update', $mediaContent) }}" class="editor-form" enctype="multipart/form-data" data-media-upload-form>
        @method('PUT')
        @include('cms-admin::media-contents._form')
    </form>

    {{-- The Retry button in the form above belongs to this one (a form cannot sit inside another form). --}}
    @if($mediaContent->processing_status === 'failed')
        <form id="retry-audio-form" method="POST" action="{{ route('admin.media-contents.retry-audio', $mediaContent) }}" data-retry-audio data-processing-label="{{ __('Audio processing') }}" hidden>@csrf</form>
    @endif
@endsection
