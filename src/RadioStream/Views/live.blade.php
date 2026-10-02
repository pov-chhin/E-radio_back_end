@extends('radio-stream::layout')
@section('title', 'Live Studio')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">AzuraCast · {{ config('services.azuracast.station_id') }}</p><h1>Live Studio</h1><p>Monitor the live input and control AzuraCast broadcast services.</p></div><div class="heading-actions"><a class="primary-button" href="{{ $azuracast_url }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Open AzuraCast</a>@if($azuracast_authenticated)<form method="POST" action="{{ route('dashboard.station.restart') }}" onsubmit="return confirm('Restart broadcasting? Connected listeners may be interrupted.')">@csrf<button class="secondary-button"><i class="fa fa-refresh"></i> Restart broadcasting</button></form>@endif</div></div>
    <section class="studio-status {{ $isLive ? 'is-live' : '' }}">
        <div><span class="broadcast-orb"><i></i></span><div><small>Broadcast source</small><h2>{{ !$station['is_online'] ? 'Station is offline' : ($isLive ? (($station['streamer_name'] ?: 'Live DJ').' is on air') : 'AzuraCast AutoDJ is on air') }}</h2><p>{{ $station['format'] ?: 'Audio' }} · {{ $station['bitrate'] ? $station['bitrate'].' kbps' : 'bitrate unavailable' }} · {{ $station['listeners'] }} listener(s)</p></div></div>
        <div class="session-clock"><small>Source</small><strong>{{ $station['source'] }}</strong></div>
    </section>
    <div class="studio-grid">
        <section class="panel mixer-panel"><div class="panel-head"><div><h3>Input mixer</h3><p>Real-time audio levels</p></div><span class="healthy-pill">● Signal clean</span></div>
            <div class="channel-strip"><div><b>{{ $station['is_live'] ? 'Live streamer' : 'AutoDJ / Liquidsoap' }}</b><small>{{ strtoupper($station['backend']) }}</small></div><div class="level-meter"><i style="width:{{ $station['is_online'] ? 78 : 0 }}%"></i><span style="left:{{ $station['is_online'] ? 78 : 0 }}%"></span></div><strong>{{ $station['is_online'] ? 'ON AIR' : 'OFF' }}</strong></div>
        </section>
        <aside class="stacked-panels">
            <section class="panel"><div class="panel-head"><div><h3>Connection</h3><p>AzuraCast stream health</p></div></div><dl class="detail-list"><div><dt>Status</dt><dd class="green-text">● {{ $station['is_online'] ? 'Connected' : 'Offline' }}</dd></div><div><dt>Codec</dt><dd>{{ $station['format'] ?: '—' }} · {{ $station['bitrate'] ? $station['bitrate'].' kbps' : '—' }}</dd></div><div><dt>Frontend</dt><dd>{{ strtoupper($station['frontend']) }}</dd></div><div><dt>Backend</dt><dd>{{ ucfirst($station['backend']) }}</dd></div><div><dt>Listeners</dt><dd>{{ number_format($station['listeners']) }}</dd></div></dl></section>
            <section class="panel fallback-card"><span class="shield-icon"><i class="fa fa-check"></i></span><div><h3>Automatic fallback</h3><p>AutoDJ takes over instantly if this input disconnects.</p></div><label class="switch"><input type="checkbox" checked><span></span></label></section>
        </aside>
    </div>
    <section class="panel service-control-panel">
        <div class="panel-head"><div><h3>AzuraCast service controls</h3><p>Control the broadcasting frontend and AutoDJ for {{ config('services.azuracast.station_id') }}.</p></div></div>
        @if($azuracast_authenticated)<div class="service-control-grid">
            @foreach([['frontend', 'Broadcast frontend', 'Icecast / Shoutcast'], ['backend', 'AutoDJ', 'Liquidsoap']] as [$service, $label, $description])
                <article>
                    <div><b>{{ $label }}</b><small>{{ $description }}</small></div>
                    <div class="service-control-actions">
                        @foreach(['start', 'restart', 'stop'] as $action)
                            <form method="POST" action="{{ route('dashboard.services.control', [$service, $action]) }}" @if($action === 'stop') onsubmit="return confirm('Stop {{ $label }}?')" @endif>
                                @csrf
                                <button class="{{ $action === 'stop' ? 'danger-button' : 'secondary-button' }} compact">{{ ucfirst($action) }}</button>
                            </form>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>@else<p class="empty-state">Add an AzuraCast API key to enable start, restart, and stop controls.</p>@endif
    </section>
    <section class="panel source-panel"><div class="panel-head"><div><h3>Active source</h3><p>Reported directly by AzuraCast now-playing.</p></div></div><div class="source-flow"><article class="active"><span>1</span><div><b>{{ $station['is_live'] ? 'Live streamer' : 'AutoDJ' }}</b><small>{{ $station['show'] }} · {{ $station['is_online'] ? 'On air' : 'Offline' }}</small></div><em>{{ $station['is_live'] ? 'LIVE' : 'AUTO' }}</em></article></div></section>
@endsection
