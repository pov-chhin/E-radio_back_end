<?php

namespace Src\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\MediaStorage;
use Aws\Exception\AwsException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Src\Catalog\Services\YoutubeCookieConverter;
use Src\Catalog\Services\YoutubeCookieStore;
use Src\Setting\Models\LanguageSetting;
use Src\Setting\Models\RadioScraperSetting;
use Src\Setting\Models\RadioStudioSetting;
use Src\Setting\Models\RustfsSetting;
use Src\Setting\Models\StorageSetting;
use Throwable;

class SettingController extends Controller
{
    public function edit(): View
    {
        $storageSetting = StorageSetting::firstOrFail();
        $rustfsSetting = RustfsSetting::firstOrFail();
        $youtubeCookies = $this->savedCredential($storageSetting, 'youtube_cookies');

        return view('setting::edit', [
            'setting' => RadioScraperSetting::firstOrFail(),
            'storageSetting' => $storageSetting,
            'rustfsSetting' => $rustfsSetting,
            'languageSetting' => LanguageSetting::firstOrFail(),
            'studioSetting' => RadioStudioSetting::firstOrFail(),
            // Secrets are never sent back to the browser — the page only needs to know whether one is saved.
            's3Key' => $this->savedCredential($storageSetting, 's3_key'),
            's3HasSecret' => $this->savedCredential($storageSetting, 's3_secret') !== null,
            'rustfsKey' => $this->savedCredential($rustfsSetting, 's3_key'),
            'rustfsHasSecret' => $this->savedCredential($rustfsSetting, 's3_secret') !== null,
            // True while the keys come from .env because none are saved here.
            'rustfsFromEnv' => $rustfsSetting->hasEnvCredentials()
                && $this->savedCredential($rustfsSetting, 's3_key') === null
                && $this->savedCredential($rustfsSetting, 's3_secret') === null,
            // The RustFS storage type can only be picked once its connection is complete.
            'rustfsReady' => $rustfsSetting->s3Disk() !== null,
            'youtubeCookiesSaved' => $youtubeCookies !== null,
            // Only names, domains and expiry reach the page — the cookie values never do.
            'youtubeCookies' => $youtubeCookies === null ? [] : YoutubeCookieStore::list($youtubeCookies),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $data['country'] = strtoupper($data['country']);

        $setting = RadioScraperSetting::firstOrFail();
        $setting->update($data);
        $setting->applyToConfig();

        return back()->with('status', __('Radio scraper settings saved.'));
    }

    public function updateStorage(Request $request): RedirectResponse
    {
        $setting = StorageSetting::firstOrFail();
        $useS3 = $request->input('driver') === 's3';

        // Folder path relative to the disk root — letters, numbers, dashes,
        // underscores and slashes only, and no ".." segment, so this can
        // never be steered outside the disk's own root.
        $folder = ['required', 'string', 'max:255', 'regex:/^(?!.*\.\.)[A-Za-z0-9_\-\/]+$/'];

        $data = $request->validate([
            'driver' => [
                'required',
                Rule::in(StorageSetting::DRIVERS),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'rustfs' && RustfsSetting::firstOrFail()->s3Disk() === null) {
                        $fail(__('Set up the RustFS connection in the RustFS tab first.'));
                    }
                },
            ],
            'image_directory' => $folder,
            'audio_directory' => $folder,
            ...$this->connectionRules('s3', $setting, required: $useS3, endpointRequired: false),
        ]);

        $setting->fill([
            'driver' => $data['driver'],
            'image_directory' => $data['image_directory'],
            'audio_directory' => $data['audio_directory'],
            ...$this->connectionAttributes('s3', $data, $request),
        ]);
        $setting->applyToConfig();

        // Refuse to switch to storage that cannot be written to: every upload afterwards would fail.
        if ($disk = $setting->remoteDisk()) {
            if ($error = $this->connectionError($disk)) {
                return back()->withInput($request->except('s3_secret'))->withErrors(['storage_connection' => $error]);
            }
        }

        $setting->save();

        return $this->withPublicReadCheck(
            back()
                ->with('status', $setting->driver === 'local' ? __('Storage settings saved.') : __('Storage settings saved — connected to the bucket.'))
                ->with('settings_tab', 'storage'),
            $setting->remoteDisk(),
        );
    }

    /** Saves a Netscape-format YouTube cookies.txt encrypted in the database for yt-dlp. */
    public function updateYoutube(Request $request, YoutubeCookieStore $store): RedirectResponse
    {
        $data = $request->validate([
            // A browser's JSON export can hold every site's cookies, so it is bigger than the cookies.txt it becomes.
            'youtube_cookies_file' => ['nullable', 'file', 'max:8192'],
            'remove_youtube_cookies' => ['nullable', 'boolean'],
        ]);
        $setting = StorageSetting::firstOrFail();

        if ($request->boolean('remove_youtube_cookies')) {
            $setting->update(['youtube_cookies' => null]);

            return back()->with('status', __('YouTube cookies removed.'))->with('settings_tab', 'youtube');
        }

        $file = $request->file('youtube_cookies_file');

        if ($file === null) {
            return back()->withErrors(['youtube_cookies_file' => __('Choose a cookies.txt file to upload.')])->with('settings_tab', 'youtube');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        // The JSON a browser extension exports is converted here (YouTube and Google cookies only are kept).
        if (YoutubeCookieConverter::looksLikeJson($contents)) {
            $contents = YoutubeCookieConverter::fromJson($contents) ?? '';
        }

        if (! YoutubeCookieStore::valid($contents)) {
            return back()->withErrors([
                'youtube_cookies_file' => __('That is not a valid cookies export containing YouTube cookies (a Netscape cookies.txt, or the JSON your browser extension exports).'),
            ])->with('settings_tab', 'youtube');
        }

        $retried = $store->save($contents);

        $status = $retried > 0
            ? trans_choice('YouTube cookies saved securely. Retrying :count failed audio fetch.|YouTube cookies saved securely. Retrying :count failed audio fetches.', $retried, ['count' => $retried])
            : __('YouTube cookies saved securely.');

        return back()->with('status', $status)->with('settings_tab', 'youtube');
    }

    /**
     * Makes the files of one remote storage publicly readable (the "Make files public" button) — images and audio
     * are loaded straight from the bucket, so a bucket that only the app can read leaves broken links everywhere.
     */
    public function makePublic(Request $request): RedirectResponse
    {
        $disk = $request->validate([
            'disk' => ['required', Rule::in([MediaStorage::S3, MediaStorage::RUSTFS])],
        ])['disk'];
        $tab = [MediaStorage::RUSTFS => 'rustfs'][$disk] ?? 'storage';
        $back = back()->with('settings_tab', $tab);

        if (! config("filesystems.disks.{$disk}")) {
            return $back->with('error', __('Set up and save that connection first.'));
        }

        try {
            MediaStorage::makePublic($disk);
        } catch (Throwable $exception) {
            return $back->with('error', __('Could not change the bucket policy: :reason The access key needs permission to set bucket policies — or make the image and audio folders readable in the storage server\'s own console instead.', ['reason' => $this->storageFailureReason($exception)]));
        }

        return $this->withPublicReadCheck($back->with('status', __('Bucket policy updated — image and audio files can be read without signing in.')), $disk);
    }

    /** Adds the reason files cannot be read publicly, if that is so, to a redirect that already carries the save result. */
    private function withPublicReadCheck(RedirectResponse $response, ?string $disk): RedirectResponse
    {
        $problem = $disk ? MediaStorage::publicReadError($disk) : null;

        return $problem ? $response->with('public_read_warning', ['disk' => $disk, 'message' => $problem]) : $response;
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(LanguageSetting::LOCALES))],
        ]);

        LanguageSetting::firstOrFail()->update($data);

        return back()->with('status', __('Language settings saved.'))->with('settings_tab', 'language');
    }

    /** The sidebar's Radio Studio link. http or https only, since it goes straight into a link; empty hides the link. */
    public function updateRadioStudio(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['url' => ['nullable', 'url:http,https', 'max:2048']],
            [],
            ['url' => __('Radio Studio address')],
        );

        RadioStudioSetting::firstOrFail()->update($data);

        return back()->with('status', __('Radio Studio settings saved.'))->with('settings_tab', 'radio-studio');
    }

    public function updateRustfs(Request $request): RedirectResponse
    {
        return $this->updateServerConnection($request, RustfsSetting::firstOrFail(), 'rustfs', 'RustFS', MediaStorage::RUSTFS);
    }

    /**
     * Saves a self-hosted store's connection (the RustFS tab). $prefix names its form inputs and tab,
     * $disk is the Laravel disk the connection defines; nothing is stored unless a small file can really be
     * written to the bucket.
     */
    private function updateServerConnection(Request $request, RustfsSetting $connection, string $prefix, string $label, string $disk): RedirectResponse
    {
        $data = $request->validate($this->connectionRules($prefix, $connection, required: true, endpointRequired: true));

        $connection->fill($this->connectionAttributes($prefix, $data, $request));
        $connection->applyToConfig();

        if ($error = $this->connectionError($disk)) {
            return back()->withInput($request->except("{$prefix}_secret"))->withErrors(["{$prefix}_connection" => $error]);
        }

        $connection->save();
        // If this store is the selected storage, uploads follow the new connection straight away.
        StorageSetting::firstOrFail()->applyToConfig();

        return $this->withPublicReadCheck(
            back()->with('status', __(':label connection saved — connected to the bucket.', ['label' => $label]))->with('settings_tab', $prefix),
            $disk,
        );
    }

    /**
     * Validation rules for one set of S3-style connection fields. $prefix names the form's inputs ('s3' on the
     * Storage tab, 'rustfs' on its tab) so the forms never share old input or errors.
     *
     * @return array<string, array<int, mixed>>
     */
    private function connectionRules(string $prefix, StorageSetting|RustfsSetting $setting, bool $required, bool $endpointRequired): array
    {
        $savedKey = $this->savedCredential($setting, 's3_key');
        $savedSecret = $this->savedCredential($setting, 's3_secret');
        // The environment's key and secret stand in until some are saved, so both may stay blank — but a key typed
        // in must come with its secret (and the other way round), or the pair would not match.
        $fromEnv = $savedKey === null && $savedSecret === null && $setting->hasEnvCredentials();

        return [
            // Left blank, the saved key and secret are kept — they are never shown again once stored.
            "{$prefix}_key" => [Rule::requiredIf($required && $savedKey === null && ! $fromEnv), ...($fromEnv ? ["required_with:{$prefix}_secret"] : []), 'nullable', 'string', 'max:255'],
            "{$prefix}_secret" => [Rule::requiredIf($required && $savedSecret === null && ! $fromEnv), ...($fromEnv ? ["required_with:{$prefix}_key"] : []), 'nullable', 'string', 'max:255'],
            "{$prefix}_bucket" => [Rule::requiredIf($required), 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            "{$prefix}_region" => [Rule::requiredIf($required), 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            "{$prefix}_endpoint" => [Rule::requiredIf($endpointRequired), 'nullable', 'url', 'max:255'],
            "{$prefix}_url" => ['nullable', 'url', 'max:255'],
            "{$prefix}_use_path_style_endpoint" => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Maps the validated `{$prefix}_*` input onto the model's `s3_*` columns. A blank key or secret is left out,
     * so the saved one stays.
     *
     * @return array<string, mixed>
     */
    private function connectionAttributes(string $prefix, array $data, Request $request): array
    {
        $attributes = ['s3_use_path_style_endpoint' => $request->boolean("{$prefix}_use_path_style_endpoint")];

        foreach (['bucket', 'region', 'endpoint', 'url'] as $field) {
            if (array_key_exists("{$prefix}_{$field}", $data)) {
                $attributes["s3_{$field}"] = $data["{$prefix}_{$field}"];
            }
        }

        foreach (['key', 'secret'] as $credential) {
            if (filled($data["{$prefix}_{$credential}"] ?? null)) {
                $attributes["s3_{$credential}"] = $data["{$prefix}_{$credential}"];
            }
        }

        return $attributes;
    }

    /** Tries to write to the disk; returns a message for the admin when that fails, null when it works. */
    private function connectionError(string $disk): ?string
    {
        try {
            MediaStorage::probe($disk);
        } catch (Throwable $exception) {
            return __('Could not write to that bucket: :reason', ['reason' => $this->storageFailureReason($exception)]);
        }

        return null;
    }

    /**
     * The storage provider's own reason. Flysystem's message leads with the full request URL, so cutting it to
     * a fixed length used to drop the reason (the part after "AWS HTTP error:") before the admin ever saw it.
     */
    private function storageFailureReason(Throwable $exception): string
    {
        for ($cause = $exception; $cause; $cause = $cause->getPrevious()) {
            if (! $cause instanceof AwsException) {
                continue;
            }

            if ($cause->getAwsErrorCode()) {
                return trim("{$cause->getAwsErrorCode()}: {$cause->getAwsErrorMessage()} (HTTP {$cause->getStatusCode()})");
            }

            // The reply was not an S3 error document at all — typically a web page, because the address is a web
            // console or a proxy rather than the storage API. The SDK's own wording only says "parsing XML failed".
            if (str_contains($cause->getMessage(), 'Error parsing XML')) {
                $status = $cause->getStatusCode() ?: __('no status');

                return __('The address answered, but not like an S3 API (HTTP :status, no S3 error in the reply). Check the endpoint is the storage API address — usually port 9000 — and not the web console.', ['status' => $status]);
            }

            // The reason follows "AWS HTTP error:" (a newline, not a space, separates it for cURL failures).
            return Str::limit(preg_replace('/\s+/', ' ', trim(Str::after($cause->getMessage(), 'AWS HTTP error:'))), 240);
        }

        return Str::limit($exception->getMessage(), 240);
    }

    /** A saved, encrypted credential — or null if there is none or it can no longer be decrypted. */
    private function savedCredential(StorageSetting|RustfsSetting $setting, string $attribute): ?string
    {
        try {
            return $setting->{$attribute} ?: null;
        } catch (DecryptException) {
            return null;
        }
    }
}
