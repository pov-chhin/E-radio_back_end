@extends('cms-admin::layout')

@section('title', 'New Category')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>New Category</h1>
            <p>Add a top-level section for listeners to browse.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="panel form-card">
        @include('cms-admin::categories._form')
    </form>
@endsection
