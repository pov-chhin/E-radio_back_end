@extends('radio-stream::layout')
@section('title', 'Stations')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">AzuraCast network</p><h1>Your stations</h1><p>Live station health and audiences from AzuraCast.</p></div><a class="primary-button" href="{{ $azuracast_url }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Manage in AzuraCast</a></div>
    <div class="toolbar"><div class="search-field"><span class="fa fa-search search-icon"></span><input placeholder="Search stations…"></div><button class="filter-button">All statuses <i class="fa fa-chevron-down"></i></button><span>{{ count($stations) }} stations</span></div>
    <section class="station-grid">
        @foreach($stations as $item)<article class="station-card">
            <div class="station-cover" style="--station-color:{{ $item['color'] }}"><span class="brand-mark"><i></i><i></i><i></i><i></i></span><small>{{ $item['freq'] }}</small><div></div></div>
            <div class="station-card-body"><div class="station-title"><div><h2>{{ $item['name'] }}</h2><p>{{ $item['genre'] }}</p></div></div><div class="station-meta"><span><i class="status-dot"></i>{{ $item['status'] }}</span><span class="fa fa-users">{{ number_format($item['listeners']) }}</span></div><div class="mini-wave">@foreach([20,35,28,62,45,75,38,58,80,42,68,32,54,72,40,62,34,52,70,45] as $h)<i style="height:{{$h}}%"></i>@endforeach</div><a href="{{ route('dashboard.station.show', $item['id']) }}">View dashboard <i class="fa fa-angle-right"></i></a><a href="{{ $item['public_player_url'] ?: $item['listen_url'] }}" target="_blank" rel="noopener">Open AzuraCast player <i class="fa fa-external-link"></i></a></div>
        </article>@endforeach
        @if(empty($stations))<p class="empty-state">No public AzuraCast stations were returned.</p>@endif
    </section>
@endsection
