{{-- Shared now-playing card --}}
<section class="now-card gradient-card">
    <div class="now-art">@if($station['art'])<img src="{{ $station['art'] }}" alt="Album artwork">@else<span>AW</span>@endif<div class="vinyl-lines"></div></div>
    <div class="now-copy">
        <span class="live-label"><i></i>{{ !$station['is_online'] ? 'OFFLINE' : ($isLive ? 'LIVE DJ' : 'AUTODJ ON AIR') }}</span>
        <h2>{{ $station['now_playing'] }}</h2><p>{{ $station['artist'] }}</p>
        <div class="show-line"><span class="host-avatar">AZ</span><span><small>AzuraCast source</small><b>{{ $station['show'] }}</b></span></div>
    </div>
    <div class="wave-area">
        <div class="waveform" aria-hidden="true">
            @foreach([28,48,22,65,84,36,55,92,62,32,73,45,88,51,69,30,78,56,91,42,64,26,72,48,82,35,60,90,52,70,32,58,76,44,66,30,54,80,48,62] as $height)
                <i style="height: {{ $height }}%"></i>
            @endforeach
        </div>
        <div class="time-track"><span>{{ $station['started_at'] }}</span><div><i style="width: 61%"></i></div><span>{{ $station['ends_at'] }}</span></div>
    </div>
    <button class="round-play" data-play-toggle aria-label="Listen live"><span class="play-triangle"></span></button>
</section>
