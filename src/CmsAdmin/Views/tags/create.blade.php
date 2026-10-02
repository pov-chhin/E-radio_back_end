@extends('cms-admin::layout')

@section('title', 'New Tag')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>New Tag</h1>
            <p>Add a tag anywhere in the tree — leave parent blank for a top-level tag.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.tags.store') }}" class="panel form-card">
        @include('cms-admin::tags._form')
    </form>
@endsection
