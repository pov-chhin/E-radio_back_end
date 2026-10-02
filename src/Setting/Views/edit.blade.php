@extends('cms-admin::layout')

@section('title', 'Settings')

@php
    $activeTab = $errors->has('image_directory') || $errors->has('audio_directory') ? 'storage' : 'radio-scraper';
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Control center</p>
            <h1>Settings</h1>
        </div>
    </div>

    <div class="settings-layout">
        <nav class="settings-nav">
            <button type="button" class="{{ $activeTab === 'radio-scraper' ? 'active' : '' }}" data-settings-tab="radio-scraper">Radio Scraper</button>
            <button type="button" class="{{ $activeTab === 'storage' ? 'active' : '' }}" data-settings-tab="storage">Storage</button>
        </nav>

        <div>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="panel settings-form" data-settings-panel="radio-scraper" @unless($activeTab === 'radio-scraper') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>Radio Scraper Settings</h3>
                    <p>Controls the radio-browser.info integration used by RadioSceaper — the same values previously only set via RADIO_BROWSER_ENABLED / RADIO_BROWSER_COUNTRY in .env.</p>

                    <label class="toggle-setting">
                        <span><b>Enable radio-browser.info scraping</b><small>Pull stations automatically from radio-browser.info</small></span>
                        <span class="switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled))><i></i></span>
                    </label>

                    <div class="form-grid" style="grid-template-columns:120px 1fr">
                        <label>Country code
                            <input id="country" name="country" type="text" maxlength="2"
                                   value="{{ old('country', $setting->country) }}" required
                                   style="text-transform:uppercase">
                            @error('country') <em>{{ $message }}</em> @enderror
                        </label>
                        <p class="muted" style="align-self:end;font-size:9px;margin:0 0 9px">Two-letter ISO country code, e.g. KH, US, FR.</p>
                    </div>
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">Save</button></div>
            </form>

            <form method="POST" action="{{ route('admin.settings.storage.update') }}" class="panel settings-form" data-settings-panel="storage" @unless($activeTab === 'storage') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>Storage Settings</h3>
                    <p>Where new uploads for media content are saved on the public disk (storage/app/public/…). Changing a folder only affects uploads from that point on — files already saved stay where they are.</p>

                    <div class="form-grid">
                        <label>Image folder
                            <input name="image_directory" type="text" value="{{ old('image_directory', $storageSetting->image_directory) }}" required>
                            @error('image_directory') <em>{{ $message }}</em> @enderror
                        </label>
                        <label>Audio folder
                            <input name="audio_directory" type="text" value="{{ old('audio_directory', $storageSetting->audio_directory) }}" required>
                            @error('audio_directory') <em>{{ $message }}</em> @enderror
                        </label>
                    </div>
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">Save</button></div>
            </form>
        </div>
    </div>
@endsection
