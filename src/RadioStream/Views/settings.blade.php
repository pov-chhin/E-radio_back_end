@extends('radio-stream::layout')
@section('title', 'Settings')
@php
    $azuracastFieldError = $errors->hasAny(['url', 'station_id', 'api_key', 'timeout']);
    $activeTab = $azuracastFieldError ? 'integrations' : 'general';
@endphp
@section('content')
    @include('radio-stream::partials.page-heading', ['title'=>'Station settings','subtitle'=>'Live configuration reported by AzuraCast.','eyebrow'=>'AzuraCast connection'])
    <div class="settings-layout">
        <nav class="settings-nav">
            <button type="button" class="{{ $activeTab === 'general' ? 'active' : '' }}" data-settings-tab="general">General</button>
            <button type="button" class="{{ $activeTab === 'stream' ? 'active' : '' }}" data-settings-tab="stream">Stream</button>
            <button type="button" class="{{ $activeTab === 'integrations' ? 'active' : '' }}" data-settings-tab="integrations">Integration</button>
        </nav>
        <div class="panel settings-form">
            <div data-settings-panel="general" @unless($activeTab === 'general') hidden @endunless>
                <div class="form-section"><h3>Station identity</h3><p>Edit these values in AzuraCast so it remains the source of truth.</p><div class="form-grid">
                    <label>Station name<input value="{{ $station['name'] }}" readonly></label>
                    <label>Station ID<input value="{{ $station['id'] ?? '—' }}" readonly></label>
                    <label class="full">Description<textarea readonly>{{ $station['description'] ?: 'No description configured.' }}</textarea></label>
                    <label>Timezone<input value="{{ $station['timezone'] }}" readonly></label>
                    <label>Status<input value="{{ $station['is_online'] ? 'Online' : 'Offline' }}" readonly></label>
                </div></div>
            </div>
            <div data-settings-panel="stream" @unless($activeTab === 'stream') hidden @endunless>
                <div class="form-section"><h3>Stream & encoder</h3><p>Public listening and broadcast components from AzuraCast.</p><div class="form-grid">
                    <label class="full">Primary stream URL<input value="{{ $station['stream_url'] }}" readonly></label>
                    <label>Frontend<input value="{{ strtoupper($station['frontend']) }}" readonly></label>
                    <label>Backend<input value="{{ ucfirst($station['backend']) }}" readonly></label>
                    <label>Codec<input value="{{ $station['format'] ?: '—' }}" readonly></label>
                    <label>Bitrate<input value="{{ $station['bitrate'] ? $station['bitrate'].' kbps' : '—' }}" readonly></label>
                </div></div>
            </div>
            <div data-settings-panel="integrations" @unless($activeTab === 'integrations') hidden @endunless>
                <div class="form-section"><h3>Integration</h3><p>The E-Radio dashboard reads directly from your local AzuraCast API.</p>
                    <div class="integration-row"><span class="integration-logo">A</span><div><b>AzuraCast</b><small>{{ $azuracast_url }} · station {{ config('services.azuracast.station_id') }}</small></div><span class="healthy-pill">● {{ $azuracast_connected ? 'Connected' : 'Disconnected' }}</span></div>
                    <div class="integration-row"><span class="integration-logo"><i class="fa fa-key"></i></span><div><b>Management API</b><small>{{ $azuracast_authenticated ? 'Authenticated library, analytics, and controls enabled' : 'Add AZURACAST_API_KEY for protected features' }}</small></div><span class="healthy-pill">{{ $azuracast_authenticated ? 'Enabled' : 'Read only' }}</span></div>
                </div>
                <form method="POST" action="{{ route('dashboard.settings.azuracast.update') }}" class="form-section">
                    @csrf
                    @method('PUT')
                    <h3>Connect AzuraCast</h3>
                    <p>Saved here instead of editing .env by hand — these values are stored in the database and override .env on every request.</p>
                    <div class="form-grid">
                        <label class="full">AzuraCast URL
                            <input type="url" name="url" value="{{ old('url', config('services.azuracast.url')) }}" placeholder="http://172.30.30.206" required>
                            @error('url') <em>{{ $message }}</em> @enderror
                        </label>
                        <label>Station ID
                            <input type="text" name="station_id" value="{{ old('station_id', config('services.azuracast.station_id')) }}" placeholder="ams_fm97radio" required>
                            @error('station_id') <em>{{ $message }}</em> @enderror
                        </label>
                        <label>Timeout (seconds)
                            <input type="number" name="timeout" min="1" max="60" value="{{ old('timeout', config('services.azuracast.timeout')) }}" required>
                            @error('timeout') <em>{{ $message }}</em> @enderror
                        </label>
                        <label class="full">API key
                            <input type="password" name="api_key" value="{{ old('api_key') }}" placeholder="{{ config('services.azuracast.api_key') ? 'Leave blank to keep the current key' : 'Add AZURACAST_API_KEY for protected features' }}" autocomplete="off">
                            @error('api_key') <em>{{ $message }}</em> @enderror
                        </label>
                    </div>
                    <div class="form-actions"><span>Leaving the API key blank keeps the one already saved.</span><button type="submit" class="primary-button">Save connection</button></div>
                </form>
            </div>
            <div class="form-actions"><span>AzuraCast is the source of truth.</span><a class="primary-button" href="{{ $azuracast_url }}" target="_blank" rel="noopener">Manage in AzuraCast <i class="fa fa-external-link"></i></a></div>
        </div>
    </div>
@endsection
