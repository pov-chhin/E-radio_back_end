@extends('cms-admin::layout')

@section('title', 'Add Channel')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Network</p>
            <h1>Add Channel</h1>
            <p>Manually add a station instead of waiting on the next radio-browser.info sync.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.radio-channels.store') }}" class="panel form-card" enctype="multipart/form-data">
        @include('cms-admin::radio-channels._form')
    </form>
@endsection
