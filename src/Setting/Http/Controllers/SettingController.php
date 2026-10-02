<?php

namespace Src\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\Setting\Models\RadioScraperSetting;
use Src\Setting\Models\StorageSetting;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('setting::edit', [
            'setting' => RadioScraperSetting::firstOrFail(),
            'storageSetting' => StorageSetting::firstOrFail(),
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
        // Folder path relative to the public disk root — letters, numbers,
        // dashes, underscores and slashes only, and no ".." segment, so this
        // can never be steered outside the disk's own root.
        $data = $request->validate([
            'image_directory' => ['required', 'string', 'max:255', 'regex:/^(?!.*\.\.)[A-Za-z0-9_\-\/]+$/'],
            'audio_directory' => ['required', 'string', 'max:255', 'regex:/^(?!.*\.\.)[A-Za-z0-9_\-\/]+$/'],
        ]);

        $setting = StorageSetting::firstOrFail();
        $setting->update($data);
        $setting->applyToConfig();

        return back()->with('status', 'Storage settings saved.');
    }
}
