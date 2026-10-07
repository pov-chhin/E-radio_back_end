@extends('cms-admin::layout')

@section('title', 'Edit Channel')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Network') }}</p>
            <h1>{{ __('Edit Channel') }}</h1>
            <p>{{ __('Update :name.', ['name' => $radioChannel->name]) }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.radio-channels.update', $radioChannel) }}" class="panel form-card" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::radio-channels._form')
    </form>
@endsection
