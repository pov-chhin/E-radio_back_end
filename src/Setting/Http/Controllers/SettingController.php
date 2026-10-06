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
use Src\Setting\Models\RadioScraperSetting;
use Src\Setting\Models\RustfsSetting;
use Src\Setting\Models\StorageSetting;
use Throwable;

class SettingController extends Controller
{
    public function edit(): View
    {
        $storageSetting = StorageSetting::firstOrFail();
        $rustfsSetting = RustfsSetting::firstOrFail();

        return view('setting::edit', [
            'setting' => RadioScraperSetting::firstOrFail(),
            'storageSetting' => $storageSetting,
            'rustfsSetting' => $rustfsSetting,
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

        return back()->with('status', 'Radio scraper settings saved.');
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
                        $fail('Set up the RustFS connection in the RustFS tab first.');
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
        if ($setting->usesS3() || $setting->usesRustfs()) {
            $disk = $setting->usesS3() ? MediaStorage::S3 : MediaStorage::RUSTFS;

            if ($error = $this->connectionError($disk)) {
                return back()->withInput($request->except('s3_secret'))->withErrors(['storage_connection' => $error]);
            }
        }

        $setting->save();

        return back()
            ->with('status', $setting->driver === 'local' ? 'Storage settings saved.' : 'Storage settings saved — connected to the bucket.')
            ->with('settings_tab', 'storage');
    }

    public function updateRustfs(Request $request): RedirectResponse
    {
        $rustfs = RustfsSetting::firstOrFail();

        $data = $request->validate($this->connectionRules('rustfs', $rustfs, required: true, endpointRequired: true));

        $rustfs->fill($this->connectionAttributes('rustfs', $data, $request));
        $rustfs->applyToConfig();

        if ($error = $this->connectionError(MediaStorage::RUSTFS)) {
            return back()->withInput($request->except('rustfs_secret'))->withErrors(['rustfs_connection' => $error]);
        }

        $rustfs->save();
        // If RustFS is the selected storage, uploads follow the new connection straight away.
        StorageSetting::firstOrFail()->applyToConfig();

        return back()
            ->with('status', 'RustFS connection saved — connected to the bucket.')
            ->with('settings_tab', 'rustfs');
    }

    /**
     * Validation rules for one set of S3-style connection fields. $prefix names the form's inputs ('s3' on the
     * Storage tab, 'rustfs' on the RustFS tab) so the two forms never share old input or errors.
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
            return 'Could not write to that bucket: '.$this->storageFailureReason($exception);
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
