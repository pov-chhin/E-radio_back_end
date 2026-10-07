@extends('radio-stream::layout')
@section('title', 'Schedule')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ __('AzuraCast programming') }}</p><h1>{{ __('Broadcast schedule') }}</h1><p>{{ __('Upcoming playlist and streamer events reported by AzuraCast.') }}</p></div><a class="primary-button" href="{{ $azuracast_url }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> {{ __('Manage schedule') }}</a></div>
    <section class="schedule-list panel"><div class="panel-head"><div><h3>{{ __('Upcoming programs') }}</h3><p>{{ __(':count scheduled event(s)', ['count' => count($schedule)]) }}</p></div></div>@forelse($schedule as $show)<div class="schedule-item"><span class="schedule-color" style="background:{{$show['color']}}"></span><time>{{$show['time']}}<small>{{$show['end']}}</small></time><div><b>{{ __($show['title']) }}</b><small>{{$show['date']}} · {{$show['host']}}</small></div><em>{{ __($show['type']) }}</em></div>@empty<p class="empty-state">{{ __('No upcoming schedule entries. Add playlist or streamer schedules in AzuraCast.') }}</p>@endforelse</section>
@endsection
