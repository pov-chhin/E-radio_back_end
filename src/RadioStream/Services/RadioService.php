<?php

namespace Src\RadioStream\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Src\RadioSceaper\RadioSceaper;
use Src\RadioStream\Contracts\RadioDashboard;

class RadioService implements RadioDashboard
{
    public function __construct(
        private readonly AzuraCastService $azuraCast,
        private readonly RadioSceaper $scraper,
    ) {}

    public function dashboard(?string $station = null): array
    {
        $data = $this->emptyDashboard();

        if (! $this->azuraCast->configured()) {
            $data['integration_warning'] = 'AzuraCast is not configured. Set AZURACAST_URL and AZURACAST_STATION_ID.';

            return $this->withLocalState($data);
        }

        try {
            $remote = $this->azuraCast->nowPlaying($station);
            $data = $this->mapNowPlaying($data, $remote);
            $data['stations'] = $this->mapStations($this->azuraCast->stations());
            $data['schedule'] = $this->mapSchedule($this->azuraCast->schedule($station));
            $data['azuracast_connected'] = true;
            $data['azuracast_url'] = $this->azuraCast->baseUrl();
            $data['azuracast_authenticated'] = $this->azuraCast->authenticated();

            if ($this->azuraCast->authenticated()) {
                $data = $this->loadProtectedData($data, $station);
            } else {
                $data['protected_data_warning'] = 'Add AZURACAST_API_KEY to load the full media library, playlists, service health, and detailed listener analytics.';
            }
        } catch (\Throwable $error) {
            Log::warning('AzuraCast dashboard sync failed', ['message' => $error->getMessage()]);
            $data['integration_warning'] = 'AzuraCast is configured but currently unreachable. No demo data is being shown.';
        }

        if ($this->scraper->configured()) {
            try {
                $data['scraped_stations'] = $this->scraper->get(config('services.radio_browser.country', 'KH'));
            } catch (\Throwable $error) {
                Log::warning('Radio scraper sync failed', ['message' => $error->getMessage()]);
            }
        }

        return $this->withLocalState($data);
    }

    private function emptyDashboard(): array
    {
        return [
            'station' => [
                'id' => null, 'name' => 'Radio station', 'frequency' => 'Online', 'description' => '',
                'timezone' => config('app.timezone'), 'frontend' => '—', 'backend' => '—',
                'listeners' => 0, 'peak' => null, 'uptime' => '—', 'latency' => '—',
                'now_playing' => 'Nothing playing', 'artist' => '—', 'show' => 'No active playlist',
                'started_at' => '—', 'ends_at' => '—', 'source' => 'AzuraCast disconnected',
                'art' => null, 'stream_url' => '', 'public_player_url' => '', 'bitrate' => null,
                'format' => null, 'is_online' => false, 'is_live' => false, 'streamer_name' => '',
            ],
            'stations' => [], 'tracks' => [], 'schedule' => [], 'regions' => [], 'devices' => [],
            'listeners' => [], 'chart' => [], 'playlists' => [],
            'library' => ['tracks' => 0, 'duration' => 0, 'playlists' => 0, 'storage' => 0],
            'service_status' => ['frontend' => null, 'backend' => null],
            'azuracast_connected' => false, 'azuracast_authenticated' => false,
            'azuracast_url' => rtrim((string) config('services.azuracast.url'), '/'),
        ];
    }

    private function mapNowPlaying(array $data, array $remote): array
    {
        $mount = collect(data_get($remote, 'station.mounts', []))->firstWhere('is_default', true)
            ?? data_get($remote, 'station.mounts.0', []);
        $playedAt = (int) data_get($remote, 'now_playing.played_at', 0);
        $duration = (int) data_get($remote, 'now_playing.duration', 0);

        $data['station'] = array_replace($data['station'], [
            'id' => data_get($remote, 'station.id'),
            'name' => data_get($remote, 'station.name', 'Radio station'),
            'description' => data_get($remote, 'station.description', ''),
            'timezone' => data_get($remote, 'station.timezone', config('app.timezone')),
            'frontend' => data_get($remote, 'station.frontend', '—'),
            'backend' => data_get($remote, 'station.backend', '—'),
            'listeners' => (int) data_get($remote, 'listeners.current', 0),
            'now_playing' => data_get($remote, 'now_playing.song.title', 'Nothing playing') ?: 'Nothing playing',
            'artist' => data_get($remote, 'now_playing.song.artist', '') ?: 'Unknown artist',
            'show' => data_get($remote, 'now_playing.playlist', '') ?: 'AutoDJ',
            'started_at' => $playedAt > 0 ? CarbonImmutable::createFromTimestamp($playedAt)->format('H:i') : '—',
            'ends_at' => $playedAt > 0 && $duration > 0 ? CarbonImmutable::createFromTimestamp($playedAt + $duration)->format('H:i') : '—',
            'source' => data_get($remote, 'live.is_live', false) ? 'Live DJ' : 'AzuraCast AutoDJ',
            'art' => data_get($remote, 'now_playing.song.art'),
            'stream_url' => data_get($remote, 'station.listen_url', ''),
            'public_player_url' => data_get($remote, 'station.public_player_url', ''),
            'bitrate' => data_get($mount, 'bitrate'),
            'format' => strtoupper((string) data_get($mount, 'format', '')),
            'is_online' => (bool) data_get($remote, 'is_online', false),
            'is_live' => (bool) data_get($remote, 'live.is_live', false),
            'streamer_name' => data_get($remote, 'live.streamer_name', ''),
        ]);

        $history = array_merge([data_get($remote, 'now_playing')], data_get($remote, 'song_history', []));
        $data['tracks'] = collect($history)->filter()->map(function (array $item, int $index): array {
            return [
                'id' => data_get($item, 'sh_id', $index + 1),
                'title' => data_get($item, 'song.title', 'Unknown title') ?: 'Unknown title',
                'artist' => data_get($item, 'song.artist', '') ?: 'Unknown artist',
                'album' => data_get($item, 'song.album', '') ?: '—',
                'duration' => $this->duration((int) data_get($item, 'duration', 0)),
                'plays' => '—', 'art' => data_get($item, 'song.art'),
                'color' => $this->color($index), 'source' => 'history',
            ];
        })->values()->all();

        $data['chart'] = array_fill(0, 13, $data['station']['listeners']);
        $data['isLive'] = $data['station']['is_live'];

        return $data;
    }

    private function mapStations(array $stations): array
    {
        return collect($stations)->map(function (array $item, int $index): array {
            $mount = collect(data_get($item, 'station.mounts', []))->firstWhere('is_default', true)
                ?? data_get($item, 'station.mounts.0', []);

            return [
                'id' => data_get($item, 'station.id'), 'name' => data_get($item, 'station.name', 'Station'),
                'freq' => data_get($mount, 'bitrate') ? data_get($mount, 'bitrate').' kbps' : 'Online',
                'genre' => data_get($item, 'now_playing.song.genre', '') ?: (data_get($item, 'station.description', '') ?: 'AzuraCast station'),
                'listeners' => (int) data_get($item, 'listeners.current', 0),
                'status' => data_get($item, 'is_online', false) ? (data_get($item, 'live.is_live', false) ? 'Live DJ' : 'AutoDJ') : 'Offline',
                'color' => $this->color($index), 'listen_url' => data_get($item, 'station.listen_url', ''),
                'public_player_url' => data_get($item, 'station.public_player_url', ''),
            ];
        })->values()->all();
    }

    private function mapSchedule(array $schedule): array
    {
        return collect($schedule)->map(function (array $item, int $index): array {
            $start = CarbonImmutable::parse(data_get($item, 'start'));
            $end = CarbonImmutable::parse(data_get($item, 'end'));

            return [
                'id' => data_get($item, 'id'), 'time' => $start->format('H:i'), 'end' => $end->format('H:i'),
                'date' => $start->format('D, M j'),
                'title' => data_get($item, 'title', data_get($item, 'name', 'Scheduled program')),
                'host' => data_get($item, 'name', 'AzuraCast'),
                'type' => data_get($item, 'is_now', false) ? 'Live now' : ucfirst((string) data_get($item, 'type', 'playlist')),
                'is_now' => (bool) data_get($item, 'is_now', false), 'color' => $this->color($index),
            ];
        })->values()->all();
    }

    private function loadProtectedData(array $data, ?string $station = null): array
    {
        try {
            $status = $this->azuraCast->status($station);
            $data['service_status'] = ['frontend' => data_get($status, 'frontend_running'), 'backend' => data_get($status, 'backend_running')];
        } catch (\Throwable $error) {
            Log::warning('AzuraCast status sync failed', ['message' => $error->getMessage()]);
        }

        try {
            $files = $this->azuraCast->files($station);
            $data['tracks'] = collect($files)->map(fn (array $item, int $index): array => [
                'id' => data_get($item, 'id'), 'title' => data_get($item, 'title', 'Unknown title') ?: 'Unknown title',
                'artist' => data_get($item, 'artist', '') ?: 'Unknown artist', 'album' => data_get($item, 'album', '') ?: '—',
                'duration' => data_get($item, 'length_text', $this->duration((int) data_get($item, 'length', 0))),
                'plays' => '—', 'art' => data_get($item, 'art'), 'color' => $this->color($index), 'source' => 'library',
            ])->values()->all();
            $data['library']['tracks'] = count($files);
            $data['library']['duration'] = (int) collect($files)->sum('length');
        } catch (\Throwable $error) {
            Log::warning('AzuraCast media sync failed', ['message' => $error->getMessage()]);
        }

        try {
            $data['playlists'] = $this->azuraCast->playlists($station);
            $data['library']['playlists'] = count($data['playlists']);
        } catch (\Throwable $error) {
            Log::warning('AzuraCast playlist sync failed', ['message' => $error->getMessage()]);
        }

        try {
            $data['listeners'] = $this->azuraCast->listeners($station);
            $data['regions'] = $this->listenerGroups($data['listeners'], 'location.country', 'Unknown');
            $data['devices'] = $this->listenerGroups($data['listeners'], 'device.client', 'Other');
        } catch (\Throwable $error) {
            Log::warning('AzuraCast listener sync failed', ['message' => $error->getMessage()]);
        }

        return $data;
    }

    private function listenerGroups(array $listeners, string $key, string $fallback): array
    {
        $total = max(count($listeners), 1);

        return collect($listeners)->groupBy(fn (array $listener): string => (string) (data_get($listener, $key) ?: $fallback))
            ->map(fn ($items, string $name): array => ['name' => $name, 'count' => $items->count(), 'value' => (int) round(($items->count() / $total) * 100)])
            ->sortByDesc('count')->values()->all();
    }

    private function withLocalState(array $data): array
    {
        $data['isLive'] ??= false;
        $data['savedSettings'] = session('radio.settings', []);
        $data['queuedTrack'] = session('radio.queued_track');

        return $data;
    }

    private function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function color(int $index): string
    {
        return ['#ff4d67', '#8a65ff', '#28c6a0', '#3b8ef3', '#ffb84d'][$index % 5];
    }
}
