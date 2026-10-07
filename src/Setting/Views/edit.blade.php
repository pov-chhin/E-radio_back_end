@extends('cms-admin::layout')

@section('title', 'Settings')

@php
    $storageFields = ['driver', 'image_directory', 'audio_directory', 's3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url', 'storage_connection'];
    $rustfsFields = ['rustfs_key', 'rustfs_secret', 'rustfs_bucket', 'rustfs_region', 'rustfs_endpoint', 'rustfs_url', 'rustfs_connection'];
    $activeTab = match (true) {
        $errors->has('locale'), session('settings_tab') === 'language' => 'language',
        $errors->has('url'), session('settings_tab') === 'radio-studio' => 'radio-studio',
        $errors->hasAny($rustfsFields), session('settings_tab') === 'rustfs' => 'rustfs',
        $errors->hasAny($storageFields), session('settings_tab') === 'storage' => 'storage',
        default => 'radio-scraper',
    };
    $driver = old('driver', $storageSetting->driver ?? 'local');
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ __('Control center') }}</p>
            <h1>{{ __('Settings') }}</h1>
        </div>
    </div>

    <div class="settings-layout">
        <nav class="settings-nav">
            <button type="button" class="{{ $activeTab === 'radio-scraper' ? 'active' : '' }}" data-settings-tab="radio-scraper">{{ __('Radio Scraper') }}</button>
            <button type="button" class="{{ $activeTab === 'storage' ? 'active' : '' }}" data-settings-tab="storage">{{ __('Storage') }}</button>
            <button type="button" class="{{ $activeTab === 'rustfs' ? 'active' : '' }}" data-settings-tab="rustfs">RustFS</button>
            <button type="button" class="{{ $activeTab === 'language' ? 'active' : '' }}" data-settings-tab="language">{{ __('Language') }}</button>
            <button type="button" class="{{ $activeTab === 'radio-studio' ? 'active' : '' }}" data-settings-tab="radio-studio">{{ __('Radio Studio') }}</button>
        </nav>

        <div>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="panel settings-form" data-settings-panel="radio-scraper" @unless($activeTab === 'radio-scraper') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>{{ __('Radio Scraper Settings') }}</h3>
                    <p>{{ __('Controls the radio-browser.info integration used by RadioSceaper — the same values previously only set via RADIO_BROWSER_ENABLED / RADIO_BROWSER_COUNTRY in .env.') }}</p>

                    <label class="toggle-setting">
                        <span><b>{{ __('Enable radio-browser.info scraping') }}</b><small>{{ __('Pull stations automatically from radio-browser.info') }}</small></span>
                        <span class="switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled))><i></i></span>
                    </label>

                    <div class="form-grid" style="grid-template-columns:120px 1fr">
                        <label>{{ __('Country code') }}
                            <input id="country" name="country" type="text" maxlength="2"
                                   value="{{ old('country', $setting->country) }}" required
                                   style="text-transform:uppercase">
                            @error('country') <em>{{ $message }}</em> @enderror
                        </label>
                        <p class="muted" style="align-self:end;font-size:9px;margin:0 0 9px">{{ __('Two-letter ISO country code, e.g. KH, US, FR.') }}</p>
                    </div>
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">{{ __('Save') }}</button></div>
            </form>

            <form method="POST" action="{{ route('admin.settings.storage.update') }}" class="panel settings-form" data-settings-panel="storage" @unless($activeTab === 'storage') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>{{ __('Storage Settings') }}</h3>
                    <p>{{ __('Where new uploads for media content (audio and thumbnails) are saved. Changing the storage type or a folder only affects uploads from that point on — files already saved stay where they are.') }}</p>

                    <fieldset class="storage-types">
                        <legend>{{ __('Storage type') }}</legend>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="local" data-storage-driver @checked($driver === 'local')>
                            <span><b>{{ __('Local disk') }}</b><small>{{ __('Saved on this server, in storage/app/public. On Docker, files are lost when the app is redeployed unless that folder is a persistent volume.') }}</small></span>
                        </label>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="s3" data-storage-driver @checked($driver === 's3')>
                            <span><b>{{ __('S3-compatible storage') }}</b><small>{{ __('Amazon S3, Cloudflare R2, DigitalOcean Spaces and similar. Files survive redeploys and are served straight from the bucket.') }}</small></span>
                        </label>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="rustfs" data-storage-driver @checked($driver === 'rustfs') @disabled(! $rustfsReady && $driver !== 'rustfs')>
                            <span>
                                <b>RustFS</b>
                                <small>{{ __('Your own RustFS server, using the connection saved in the RustFS tab.') }}@unless($rustfsReady) <i class="storage-type-note">{{ __('Set it up in the RustFS tab first.') }}</i>@endunless</small>
                            </span>
                        </label>
                    </fieldset>
                    @error('driver') <em class="field-error">{{ $message }}</em> @enderror

                    <div class="storage-s3" data-storage-s3 @unless($driver === 's3') hidden @endunless>
                        @error('storage_connection')
                            <div class="inline-warning danger" style="margin:0 0 16px">{{ $message }}</div>
                        @enderror
                        @if($storageSetting->s3CredentialsUnreadable())
                            <div class="inline-warning" style="margin:0 0 16px">{{ __('The saved :name credentials can no longer be read (the application key has changed). Enter the access key and secret again.', ['name' => 'S3']) }}</div>
                        @endif

                        @include('setting::partials.connection-fields', [
                            'prefix' => 's3',
                            'setting' => $storageSetting,
                            'savedKey' => $s3Key,
                            'hasSecret' => $s3HasSecret,
                            'endpointHint' => 'https://<account>.r2.cloudflarestorage.com',
                        ])
                        <p class="muted storage-hint">{{ __('Leave the endpoint empty for Amazon S3. The bucket must allow public reads, because the app loads files straight from it — for Cloudflare R2, use the region "auto" and put the bucket\'s public address (r2.dev or your own domain) in Public URL. Saving tests the connection by writing and deleting a small file.') }}</p>
                    </div>

                    @unless($driver === 's3')
                        @error('storage_connection')
                            <div class="inline-warning danger" style="margin:16px 0 0">{{ $message }}</div>
                        @enderror
                    @endunless

                    <div class="form-grid" style="margin-top:20px">
                        <label>{{ __('Image folder') }}
                            <input name="image_directory" type="text" value="{{ old('image_directory', $storageSetting->image_directory) }}" required>
                            @error('image_directory') <em>{{ $message }}</em> @enderror
                        </label>
                        <label>{{ __('Audio folder') }}
                            <input name="audio_directory" type="text" value="{{ old('audio_directory', $storageSetting->audio_directory) }}" required>
                            @error('audio_directory') <em>{{ $message }}</em> @enderror
                        </label>
                    </div>
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">{{ __('Save') }}</button></div>
            </form>

            @if($storageSetting->remoteDisk())
                @include('setting::partials.public-access', ['panel' => 'storage', 'disk' => $storageSetting->remoteDisk()])
            @endif

            <form method="POST" action="{{ route('admin.settings.language.update') }}" class="panel settings-form" data-settings-panel="language" @unless($activeTab === 'language') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>{{ __('Language Settings') }}</h3>
                    <p>{{ __('The language the admin panel is shown in by default. Each admin can still choose their own from the account menu at the top right.') }}</p>

                    <fieldset class="storage-types">
                        <legend>{{ __('Default language') }}</legend>
                        @foreach(\Src\Setting\Models\LanguageSetting::LOCALES as $code => $language)
                            <label class="storage-type">
                                <input type="radio" name="locale" value="{{ $code }}" @checked(old('locale', $languageSetting->locale) === $code)>
                                <span><b>{{ $language['name'] }}</b><small>{{ $language['short'] }}</small></span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('locale') <em class="field-error">{{ $message }}</em> @enderror
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">{{ __('Save') }}</button></div>
            </form>

            <form method="POST" action="{{ route('admin.settings.radio-studio.update') }}" class="panel settings-form" data-settings-panel="radio-studio" @unless($activeTab === 'radio-studio') hidden @endunless>
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3>{{ __('Radio Studio Settings') }}</h3>
                    <p>{{ __('Where the Radio Studio link in the sidebar goes. It opens in a new tab. Leave it empty to hide the link.') }}</p>

                    <label>{{ __('Radio Studio address') }}
                        <input id="radio_studio_url" name="url" type="url" inputmode="url" maxlength="2048" placeholder="https://studio.example.com/dashboard" value="{{ old('url', $studioSetting->url) }}">
                        @error('url') <em>{{ $message }}</em> @enderror
                    </label>
                </div>

                <div class="form-actions"><span></span><button type="submit" class="primary-button">{{ __('Save') }}</button></div>
            </form>

            @include('setting::partials.server-tab', [
                'prefix' => 'rustfs',
                'name' => 'RustFS',
                'route' => 'admin.settings.rustfs.update',
                'disk' => \App\Support\MediaStorage::RUSTFS,
                'setting' => $rustfsSetting,
                'savedKey' => $rustfsKey,
                'hasSecret' => $rustfsHasSecret,
                'fromEnv' => $rustfsFromEnv,
                'ready' => $rustfsReady,
                'endpointHint' => 'http://s3.example.com',
                'hint' => __('The endpoint is the address of the RustFS S3 API, and the bucket must already exist. Leave Public URL empty to serve files from the endpoint itself, or fill it in if the bucket is published under another address. Saving tests the connection by writing and deleting a small file.'),
                'activeTab' => $activeTab,
            ])
        </div>
    </div>
@endsection
