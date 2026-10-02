@extends('cms-admin::layout')

@section('title', 'Edit Channel')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Network</p>
            <h1>Edit Channel</h1>
            <p>Update {{ $radioChannel->name }}.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.radio-channels.update', $radioChannel) }}" class="panel form-card" enctype="multipart/form-data">
        @method('PUT')
        @include('cms-admin::radio-channels._form')
    </form>
@endsection
