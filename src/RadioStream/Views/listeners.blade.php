@extends('radio-stream::layout')
@section('title', 'Listeners')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ __('AzuraCast audience') }}</p><h1>{{ __('Listeners') }}</h1><p>{{ __('Current listener data reported by AzuraCast.') }}</p></div><a class="secondary-button" href="{{ $azuracast_url }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> {{ __('Full AzuraCast reports') }}</a></div>

    @php
        $averageSession = count($listeners) ? (int) round(collect($listeners)->avg('connected_time')) : 0;
        $longestSession = count($listeners) ? (int) collect($listeners)->max('connected_time') : 0;
    @endphp
    <section class="stat-grid">
        <article class="stat-card"><span>{{ __('Listening now') }}</span><strong>{{ number_format($station['listeners']) }}</strong><small class="trend neutral">{{ __('Live public count') }}</small></article>
        <article class="stat-card"><span>{{ __('Detailed sessions') }}</span><strong>{{ $azuracast_authenticated ? number_format(count($listeners)) : __('Locked') }}</strong><small class="trend neutral">{{ $azuracast_authenticated ? __('Active connections') : __('API key required') }}</small></article>
        <article class="stat-card"><span>{{ __('Average session') }}</span><strong>{{ $azuracast_authenticated ? sprintf('%dm %02ds', intdiv($averageSession, 60), $averageSession % 60) : '—' }}</strong><small class="trend neutral">{{ __('Current listeners') }}</small></article>
        <article class="stat-card"><span>{{ __('Longest session') }}</span><strong>{{ $azuracast_authenticated ? sprintf('%dm %02ds', intdiv($longestSession, 60), $longestSession % 60) : '—' }}</strong><small class="trend neutral">{{ __('Current listeners') }}</small></article>
    </section>

    <div class="insights-grid bottom">
        <section class="panel"><div class="panel-head"><div><h3>{{ __('Top locations') }}</h3><p>{{ __('Current listener distribution by country') }}</p></div></div><div class="region-list">
            @forelse($regions as $region)<div><span>{{ $loop->iteration }}</span><b>{{ __($region['name']) }}</b><div><i style="width:{{ $region['value'] }}%"></i></div><strong>{{ number_format($region['count']) }}</strong><small>{{ $region['value'] }}%</small></div>@empty<p class="empty-state">{{ $azuracast_authenticated ? __('No listeners are currently connected.') : __('Add AZURACAST_API_KEY to load location analytics.') }}</p>@endforelse
        </div></section>
        <section class="panel"><div class="panel-head"><div><h3>{{ __('Listener clients') }}</h3><p>{{ __('Apps and browsers currently connected') }}</p></div></div><div class="region-list">
            @forelse($devices as $device)<div><span>{{ $loop->iteration }}</span><b>{{ __($device['name']) }}</b><div><i style="width:{{ $device['value'] }}%"></i></div><strong>{{ number_format($device['count']) }}</strong><small>{{ $device['value'] }}%</small></div>@empty<p class="empty-state">{{ $azuracast_authenticated ? __('No client data is available.') : __('Add AZURACAST_API_KEY to load client analytics.') }}</p>@endforelse
        </div></section>
    </div>
@endsection
