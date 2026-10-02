@extends('radio-stream::layout')
@section('title', 'Overview')
@section('content')
    <section class="overview-stations">
        <div class="panel-head">
            <div><h3>All stations</h3><p>{{ count($stations) }} station(s) connected through AzuraCast</p></div>
            <a class="text-button" href="{{ route('dashboard.stations') }}">Station details <i class="fa fa-angle-right"></i></a>
        </div>
        <div class="station-grid">
            @forelse($stations as $item)
                <article class="station-card">
                    <div class="station-cover" style="--station-color:{{ $item['color'] }}">
                        <span class="brand-mark"><i></i><i></i><i></i><i></i></span>
                        <small>{{ $item['freq'] }}</small>
                    </div>
                    <div class="station-card-body">
                        <div class="station-title"><div><h2>{{ $item['name'] }}</h2><p>{{ $item['genre'] }}</p></div></div>
                        <div class="station-meta"><span><i class="status-dot"></i>{{ $item['status'] }}</span><span class="fa fa-users">{{ number_format($item['listeners']) }}</span></div>
                        <div class="mini-wave">@foreach([20,35,28,62,45,75,38,58,80,42,68,32,54,72,40,62,34,52,70,45] as $height)<i style="height:{{ $height }}%"></i>@endforeach</div>
                        <a href="{{ route('dashboard.station.switch', $item['id']) }}">Open {{ $item['name'] }} player <i class="fa fa-angle-right"></i></a>
                    </div>
                </article>
            @empty
                <p class="empty-state">No public AzuraCast stations are available.</p>
            @endforelse
        </div>
    </section>

@endsection
