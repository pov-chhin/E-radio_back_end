@extends('cms-admin::layout')

@section('title', 'Edit Category')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>Edit Category</h1>
            <p>Update {{ $category->name }}.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="panel form-card">
        @method('PUT')
        @include('cms-admin::categories._form')
    </form>
@endsection
