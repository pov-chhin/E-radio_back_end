@extends('radio-stream::layout')
@section('title', 'Overview')
@section('content')
    <section class="overview-stations">
        <div class="panel-head">
            <div><h3>{{ __('All stations') }}</h3><p>{{ __(':count station(s) connected through AzuraCast', ['count' => count($stations)]) }}</p></div>
            <a class="text-button" href="{{ route('dashboard.stations') }}">{{ __('Station details') }} <i class="fa fa-angle-right"></i></a>
        </div>
        <div class="station-grid">
            @forelse($stations as $item)
                <article class="station-card">
                    <div class="station-cover" style="--station-color:{{ $item['color'] }}">
                        <span class="brand-mark"><i></i><i></i><i></i><i></i></span>
                        <small>{{ __($item['freq']) }}</small>
                    </div>
                    <div class="station-card-body">
                        <div class="station-title"><div><h2>{{ $item['name'] }}</h2><p>{{ __($item['genre']) }}</p></div></div>
                        <div class="station-meta"><span><i class="status-dot"></i>{{ __($item['status']) }}</span><span class="fa fa-users">{{ number_format($item['listeners']) }}</span></div>
                        <div class="mini-wave">@foreach([20,35,28,62,45,75,38,58,80,42,68,32,54,72,40,62,34,52,70,45] as $height)<i style="height:{{ $height }}%"></i>@endforeach</div>
                        <a href="{{ route('dashboard.station.switch', $item['id']) }}">{{ __('Open :name player', ['name' => $item['name']]) }} <i class="fa fa-angle-right"></i></a>
                    </div>
                </article>
            @empty
                <p class="empty-state">{{ __('No public AzuraCast stations are available.') }}</p>
            @endforelse
        </div>
    </section>

@endsection
