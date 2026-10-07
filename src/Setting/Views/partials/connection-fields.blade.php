{{--
    The fields of one S3-style connection (key, secret, bucket, region, endpoint, public URL, path-style).
    $prefix       's3' (Storage tab) or 'rustfs' (RustFS tab) — names the inputs so the two forms never share old input
    $setting      the settings row the values come from
    $savedKey     the access key ID already saved, if any;  $hasSecret whether a secret is saved
    $endpointHint placeholder text for the endpoint field
    $fromEnv      (optional) true while the key and secret come from the .env file because none are saved
--}}
<div class="form-grid">
    <label>{{ __('Access key ID') }}
        <input name="{{ $prefix }}_key" type="text" value="{{ old("{$prefix}_key", $savedKey) }}" autocomplete="off" spellcheck="false" placeholder="{{ ($fromEnv ?? false) ? __('From .env — leave blank to use it') : '' }}">
        @error("{$prefix}_key") <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Secret access key') }}
        <input name="{{ $prefix }}_secret" type="password" value="" autocomplete="new-password" placeholder="{{ $hasSecret ? __('Saved — leave blank to keep it') : (($fromEnv ?? false) ? __('From .env — leave blank to use it') : '') }}">
        @error("{$prefix}_secret") <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Bucket') }}
        <input name="{{ $prefix }}_bucket" type="text" value="{{ old("{$prefix}_bucket", $setting->s3_bucket) }}" placeholder="my-radio-media" spellcheck="false">
        @error("{$prefix}_bucket") <em>{{ $message }}</em> @enderror
    </label>
    <label>{{ __('Region') }}
        <input name="{{ $prefix }}_region" type="text" value="{{ old("{$prefix}_region", $setting->s3_region) }}" placeholder="us-east-1" spellcheck="false">
        @error("{$prefix}_region") <em>{{ $message }}</em> @enderror
    </label>
    <label><span>{{ __('Endpoint') }} @unless($endpointRequired ?? false)<span class="muted" style="font-weight:400">{{ __('— optional') }}</span>@endunless</span>
        <input name="{{ $prefix }}_endpoint" type="text" value="{{ old("{$prefix}_endpoint", $setting->s3_endpoint) }}" placeholder="{{ $endpointHint }}" spellcheck="false">
        @error("{$prefix}_endpoint") <em>{{ $message }}</em> @enderror
    </label>
    <label><span>{{ __('Public URL') }} <span class="muted" style="font-weight:400">{{ __('— optional') }}</span></span>
        <input name="{{ $prefix }}_url" type="text" value="{{ old("{$prefix}_url", $setting->s3_url) }}" placeholder="{{ $setting->envPublicUrl() ? __('From .env: :url — leave blank to use it', ['url' => $setting->envPublicUrl()]) : 'https://cdn.example.com' }}" spellcheck="false">
        @error("{$prefix}_url") <em>{{ $message }}</em> @enderror
    </label>
</div>
<div class="storage-check">
    <label><input type="checkbox" name="{{ $prefix }}_use_path_style_endpoint" value="1" @checked(old("{$prefix}_use_path_style_endpoint", $setting->s3_use_path_style_endpoint))> {{ __('Use path-style URLs (needed for RustFS)') }}</label>
</div>
