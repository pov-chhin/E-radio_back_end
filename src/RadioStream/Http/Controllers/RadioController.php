<?php

namespace Src\RadioStream\Http\Controllers;

use App\Http\Controllers\Controller;
use Src\Notification\Notifications\LiveSourceToggled;
use Src\RadioStream\Services\AzuraCastService;
use Src\RadioStream\Services\RadioService;
use Src\Setting\Models\AzuraCastSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadioController extends Controller
{
    public function __construct(
        private readonly RadioService $radio,
        private readonly AzuraCastService $azuraCast,
    ) {}

    public function index(): View { return $this->page('index'); }
    public function live(): View { return $this->page('live'); }
    public function stations(): View { return $this->page('stations'); }
    public function player(): View { return $this->page('player'); }
    public function playlist(): View { return $this->page('playlist'); }
    public function schedule(): View { return $this->page('schedule'); }
    public function listeners(): View { return $this->page('listeners'); }
    public function settings(): View { return $this->page('settings'); }

    public function stationDashboard(string $station): View
    {
        return view('radio-stream::station', $this->radio->dashboard($station) + ['stationId' => $station]);
    }

    public function switchStation(Request $request, string $station): RedirectResponse
    {
        $request->session()->put('radio.active_station', $station);

        $destinations = ['index', 'live', 'player', 'playlist', 'schedule', 'listeners', 'settings'];
        $to = $request->query('to', 'index');

        return redirect()->route('dashboard.'.(in_array($to, $destinations, true) ? $to : 'index'));
    }

    public function resetStation(Request $request): RedirectResponse
    {
        $request->session()->forget('radio.active_station');

        return redirect()->route('dashboard.index');
    }

    public function toggleLive(Request $request): RedirectResponse
    {
        $isLive = ! $request->session()->get('radio.is_live', true);
        $request->session()->put('radio.is_live', $isLive);
        $request->user('admin')->notify(new LiveSourceToggled($isLive));

        return back()->with('status', $isLive ? 'Live input connected.' : 'Live input stopped. AutoDJ is now on air.');
    }

    public function queueTrack(Request $request, string $track): RedirectResponse
    {
        $title = data_get(collect($this->radio->dashboard()['tracks'])->firstWhere('id', (int) $track), 'title', 'Track');
        $request->session()->put('radio.queued_track', $title);

        return back()->with('status', "{$title} was added to the queue.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $settings = $request->validate([
            'station_name' => ['required', 'string', 'max:80'],
            'station_url' => ['nullable', 'url', 'max:255'],
            'fallback_enabled' => ['nullable', 'boolean'],
            'crossfade' => ['required', 'integer', 'between:0,15'],
        ]);
        $settings['fallback_enabled'] = $request->boolean('fallback_enabled');
        $request->session()->put('radio.settings', $settings);

        return back()->with('status', 'Station settings saved.');
    }

    public function updateAzuraCastSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'station_id' => ['required', 'string', 'max:190'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'timeout' => ['required', 'integer', 'between:1,60'],
        ]);

        $setting = AzuraCastSetting::firstOrNew();
        // A blank API key field keeps the currently stored key — the input
        // is never pre-filled with the real secret, so an empty submit
        // should not be read as "clear it".
        if ($data['api_key'] === null || $data['api_key'] === '') {
            unset($data['api_key']);
        }
        $setting->fill($data)->save();
        $setting->applyToConfig();

        return back()->with('status', 'AzuraCast connection saved.');
    }

    public function restartStation(): RedirectResponse
    {
        return $this->runAzuraCastCommand(
            fn () => $this->azuraCast->restart(),
            'AzuraCast broadcasting services are restarting.'
        );
    }

    public function controlService(string $service, string $action): RedirectResponse
    {
        abort_unless(in_array($service, ['frontend', 'backend'], true), 404);
        abort_unless(in_array($action, ['start', 'stop', 'restart'], true), 404);

        $serviceLabel = $service === 'backend' ? 'AutoDJ' : 'broadcast frontend';

        return $this->runAzuraCastCommand(
            fn () => $this->azuraCast->control($service, $action),
            ucfirst($serviceLabel)." {$action} command sent."
        );
    }

    public function requestSong(string $requestId): RedirectResponse
    {
        abort_if($requestId === '' || strlen($requestId) > 190, 404);

        return $this->runAzuraCastCommand(
            fn () => $this->azuraCast->submitRequest($requestId),
            'Song request submitted to AzuraCast.'
        );
    }

    private function page(string $view): View
    {
        return view("radio-stream::{$view}", $this->radio->dashboard());
    }

    private function runAzuraCastCommand(callable $command, string $successMessage): RedirectResponse
    {
        try {
            $command();

            return back()->with('status', $successMessage);
        } catch (\Throwable $error) {
            report($error);

            return back()->with('error', 'AzuraCast command failed. Check the server URL, station identifier, API key, and connection.');
        }
    }
}
