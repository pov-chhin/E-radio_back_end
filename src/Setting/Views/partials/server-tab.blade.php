{{--
    One self-hosted S3-compatible store's tab (RustFS, MinIO): its connection form and save button.
    $prefix        'rustfs' or 'minio' — names the tab, its panel and its inputs
    $name          what the admin calls it ('RustFS', 'MinIO')
    $route         the route name its form is saved to
    $disk          the Laravel disk its connection defines
    $setting       the settings row the values come from
    $savedKey      the access key ID already saved, if any;  $hasSecret whether a secret is saved
    $fromEnv       true while the key and secret come from the .env file because none are saved
    $ready         whether the connection is complete enough to be chosen as the storage type
    $endpointHint  placeholder text for the endpoint field
    $hint          the help text under the fields
    $activeTab     the tab that is open
--}}
<form method="POST" action="{{ route($route) }}" class="panel settings-form" data-settings-panel="{{ $prefix }}" @unless($activeTab === $prefix) hidden @endunless>
    @csrf
    @method('PUT')

    <div class="form-section">
        <h3>{{ $name }} Settings</h3>
        <p>The connection to your {{ $name }} server. Once it is saved, choose <b>{{ $name }}</b> under Storage to upload new media content to it. Files already saved stay where they are.</p>

        @error("{$prefix}_connection")
            <div class="inline-warning danger" style="margin:0 0 16px">{{ $message }}</div>
        @enderror
        @if($setting->s3CredentialsUnreadable())
            <div class="inline-warning" style="margin:0 0 16px">The saved {{ $name }} credentials can no longer be read (the application key has changed). Enter the access key and secret again.</div>
        @endif

        @include('setting::partials.connection-fields', [
            'prefix' => $prefix,
            'setting' => $setting,
            'savedKey' => $savedKey,
            'hasSecret' => $hasSecret,
            'fromEnv' => $fromEnv,
            'endpointRequired' => true,
            'endpointHint' => $endpointHint,
        ])
        <p class="muted storage-hint">{{ $hint }}</p>
    </div>

    <div class="form-actions"><span>@if($ready && $fromEnv) <span class="badge green">Using the keys from .env</span> @elseif($ready) <span class="badge green">Connection saved</span> @else <span class="badge amber">Not set up yet</span> @endif</span><button type="submit" class="primary-button">Save</button></div>
</form>

@if($ready)
    @include('setting::partials.public-access', ['panel' => $prefix, 'disk' => $disk])
@endif
