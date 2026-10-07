@extends('radio-stream::layout')
@section('title', $station['name'])
@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">AzuraCast · {{ $stationId }}</p>
            <h1>{{ $station['name'] }}</h1>
            <p>{{ $station['description'] ?: __('AzuraCast station') }}</p>
        </div>
        <div class="heading-actions">
            @if($station['public_player_url'])
                <a class="primary-button" href="{{ $station['public_player_url'] }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> {{ __('Open public player') }}</a>
            @endif
            @if($station['id'])
                <a class="secondary-button" href="{{ $azuracast_url }}/station/{{ $station['id'] }}/streamers" target="_blank" rel="noopener"><i class="fa fa-microphone"></i> {{ __('DJ Admin') }}</a>
            @endif
            <a class="secondary-button" href="{{ route('dashboard.stations') }}"><i class="fa fa-angle-left"></i> {{ __('All stations') }}</a>
        </div>
    </div>

    <section class="stat-grid">
        <article class="stat-card"><div class="stat-icon pink fa fa-users"></div><span>{{ __('Live listeners') }}</span><strong>{{ number_format($station['listeners']) }}</strong></article>
        <article class="stat-card"><div class="stat-icon violet fa fa-signal"></div><span>{{ __('Station status') }}</span><strong>{{ $station['is_online'] ? __('Online') : __('Offline') }}</strong><small class="trend neutral">{{ __($station['source']) }}</small></article>
        <article class="stat-card"><div class="stat-icon green fa fa-clock-o"></div><span>{{ __('Broadcast frontend') }}</span><strong>{{ strtoupper($station['frontend']) }}</strong><small class="trend neutral">AzuraCast</small></article>
        <article class="stat-card"><div class="stat-icon amber fa fa-tachometer"></div><span>{{ __('Primary stream') }}</span><strong>{{ $station['bitrate'] ? $station['bitrate'].' kbps' : '—' }}</strong><small class="trend neutral">{{ $station['format'] ?: __('Unknown format') }}</small></article>
    </section>

    <section class="panel source-panel">
        <div class="panel-head"><div><h3>{{ __('Now playing') }}</h3><p>{{ __('Reported directly by AzuraCast now-playing.') }}</p></div></div>
        <div class="source-flow">
            <article class="active">
                <span>1</span>
                <div><b>{{ __($station['now_playing']) }}</b><small>{{ __($station['artist']) }} · {{ __($station['show']) }}</small></div>
                <em>{{ $station['is_live'] ? __('LIVE') : __('AUTO') }}</em>
            </article>
        </div>
    </section>

    <section class="panel upcoming-panel">
        <div class="panel-head"><div><h3>{{ __('Quick actions') }}</h3><p>{{ __('Manage :name in these tools', ['name' => $station['name']]) }}</p></div></div>
        <div class="quick-actions" style="margin-top:15px">
            <a href="{{ route('dashboard.station.switch', ['station' => $stationId, 'to' => 'player']) }}"><span class="fa fa-play"></span><b>{{ __('Web Player') }}</b><small>{{ __('Preview and customize the embed') }}</small></a>
            <a href="{{ route('dashboard.station.switch', ['station' => $stationId, 'to' => 'playlist']) }}"><span class="fa fa-music"></span><b>{{ __('Music Library') }}</b><small>{{ __('Tracks and playlists') }}</small></a>
            <a href="{{ route('dashboard.station.switch', ['station' => $stationId, 'to' => 'schedule']) }}"><span class="fa fa-calendar"></span><b>{{ __('Schedule') }}</b><small>{{ __('Programming calendar') }}</small></a>
        </div>
    </section>
@endsection
