@extends('cms-admin::layout')

@section('title', 'Settings')

@php
    $storageFields = ['driver', 'image_directory', 'audio_directory', 's3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url', 'storage_connection'];
    $rustfsFields = ['rustfs_key', 'rustfs_secret', 'rustfs_bucket', 'rustfs_region', 'rustfs_endpoint', 'rustfs_url', 'rustfs_connection'];
    $minioFields = ['minio_key', 'minio_secret', 'minio_bucket', 'minio_region', 'minio_endpoint', 'minio_url', 'minio_connection'];
    $activeTab = match (true) {
        $errors->hasAny($minioFields), session('settings_tab') === 'minio' => 'minio',
        $errors->hasAny($rustfsFields), session('settings_tab') === 'rustfs' => 'rustfs',
        $errors->hasAny($storageFields), session('settings_tab') === 'storage' => 'storage',
        default => 'radio-scraper',
    };
    $driver = old('driver', $storageSetting->driver ?? 'local');
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
            <button type="button" class="{{ $activeTab === 'rustfs' ? 'active' : '' }}" data-settings-tab="rustfs">RustFS</button>
            <button type="button" class="{{ $activeTab === 'minio' ? 'active' : '' }}" data-settings-tab="minio">MinIO</button>
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
                    <p>Where new uploads for media content (audio and thumbnails) are saved. Changing the storage type or a folder only affects uploads from that point on — files already saved stay where they are.</p>

                    <fieldset class="storage-types">
                        <legend>Storage type</legend>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="local" data-storage-driver @checked($driver === 'local')>
                            <span><b>Local disk</b><small>Saved on this server, in storage/app/public. On Docker, files are lost when the app is redeployed unless that folder is a persistent volume.</small></span>
                        </label>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="s3" data-storage-driver @checked($driver === 's3')>
                            <span><b>S3-compatible storage</b><small>Amazon S3, Cloudflare R2, DigitalOcean Spaces, MinIO and similar. Files survive redeploys and are served straight from the bucket.</small></span>
                        </label>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="rustfs" data-storage-driver @checked($driver === 'rustfs') @disabled(! $rustfsReady && $driver !== 'rustfs')>
                            <span>
                                <b>RustFS</b>
                                <small>Your own RustFS server, using the connection saved in the RustFS tab.@unless($rustfsReady) <i class="storage-type-note">Set it up in the RustFS tab first.</i>@endunless</small>
                            </span>
                        </label>
                        <label class="storage-type">
                            <input type="radio" name="driver" value="minio" data-storage-driver @checked($driver === 'minio') @disabled(! $minioReady && $driver !== 'minio')>
                            <span>
                                <b>MinIO</b>
                                <small>Your own MinIO server, using the connection saved in the MinIO tab.@unless($minioReady) <i class="storage-type-note">Set it up in the MinIO tab first.</i>@endunless</small>
                            </span>
                        </label>
                    </fieldset>
                    @error('driver') <em class="field-error">{{ $message }}</em> @enderror

                    <div class="storage-s3" data-storage-s3 @unless($driver === 's3') hidden @endunless>
                        @error('storage_connection')
                            <div class="inline-warning danger" style="margin:0 0 16px">{{ $message }}</div>
                        @enderror
                        @if($storageSetting->s3CredentialsUnreadable())
                            <div class="inline-warning" style="margin:0 0 16px">The saved S3 credentials can no longer be read (the application key has changed). Enter the access key and secret again.</div>
                        @endif

                        @include('setting::partials.connection-fields', [
                            'prefix' => 's3',
                            'setting' => $storageSetting,
                            'savedKey' => $s3Key,
                            'hasSecret' => $s3HasSecret,
                            'endpointHint' => 'https://<account>.r2.cloudflarestorage.com',
                        ])
                        <p class="muted storage-hint">Leave the endpoint empty for Amazon S3. The bucket must allow public reads, because the app loads files straight from it — for Cloudflare R2, use the region "auto" and put the bucket's public address (r2.dev or your own domain) in Public URL. Saving tests the connection by writing and deleting a small file.</p>
                    </div>

                    @unless($driver === 's3')
                        @error('storage_connection')
                            <div class="inline-warning danger" style="margin:16px 0 0">{{ $message }}</div>
                        @enderror
                    @endunless

                    <div class="form-grid" style="margin-top:20px">
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

            @if($storageSetting->remoteDisk())
                @include('setting::partials.public-access', ['panel' => 'storage', 'disk' => $storageSetting->remoteDisk()])
            @endif

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
                'hint' => 'The endpoint is the address of the RustFS S3 API, and the bucket must already exist. Leave Public URL empty to serve files from the endpoint itself, or fill it in if the bucket is published under another address. Saving tests the connection by writing and deleting a small file.',
                'activeTab' => $activeTab,
            ])

            @include('setting::partials.server-tab', [
                'prefix' => 'minio',
                'name' => 'MinIO',
                'route' => 'admin.settings.minio.update',
                'disk' => \App\Support\MediaStorage::MINIO,
                'setting' => $minioSetting,
                'savedKey' => $minioKey,
                'hasSecret' => $minioHasSecret,
                'fromEnv' => $minioFromEnv,
                'ready' => $minioReady,
                'endpointHint' => 'http://minio.example.com:9000',
                'hint' => 'The endpoint is the address of the MinIO S3 API — usually port 9000, not the web console (usually 9001) — and the bucket must already exist. Leave Public URL empty to serve files from the endpoint itself, or fill it in if the bucket is published under another address. Saving tests the connection by writing and deleting a small file.',
                'activeTab' => $activeTab,
            ])
        </div>
    </div>
@endsection
