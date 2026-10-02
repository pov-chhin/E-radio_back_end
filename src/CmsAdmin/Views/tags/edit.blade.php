@extends('cms-admin::layout')

@section('title', 'Edit Tag')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Edit Tag</h1>
            <p>Update {{ $tag->name }}.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.tags.update', $tag) }}" class="panel form-card">
        @method('PUT')
        @include('cms-admin::tags._form')
    </form>
@endsection
