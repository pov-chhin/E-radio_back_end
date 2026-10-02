@extends('cms-admin::layout')

@section('title', 'New Artist')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalog</p>
            <h1>New Artist</h1>
            <p>Add an artist profile.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.artists.store') }}" class="panel form-card" enctype="multipart/form-data">
        @include('cms-admin::artists._form')
    </form>
@endsection
