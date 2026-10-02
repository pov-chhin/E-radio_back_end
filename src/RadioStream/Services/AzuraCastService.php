<?php

namespace Src\RadioStream\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AzuraCastService
{
    public function configured(): bool
    {
        return filled(config('services.azuracast.url'));
    }

    public function authenticated(): bool
    {
        return $this->configured() && filled(config('services.azuracast.api_key'));
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.azuracast.url'), '/');
    }

    public function stations(): array
    {
        $this->ensureConfigured();

        return $this->client()->get('/api/nowplaying')->throw()->json();
    }

    public function nowPlaying(?string $station = null): array
    {
        $this->ensureConfigured();

        return $this->client()->get('/api/nowplaying/'.$this->stationIdentifier($station))->throw()->json();
    }

    public function station(): array
    {
        return $this->client()->get('/api/station/'.$this->controlStationId())->throw()->json();
    }

    public function schedule(?string $station = null, int $rows = 50): array
    {
        return $this->client()
            ->get('/api/station/'.$this->controlStationId($station).'/schedule', [
                'rows' => $rows,
                'start' => now()->format('Y-m-d'),
                'end' => now()->addDays(14)->format('Y-m-d'),
            ])
            ->throw()->json();
    }

    public function files(?string $station = null): array
    {
        return $this->authenticatedClient()->get('/api/station/'.$this->controlStationId($station).'/files')->throw()->json();
    }

    public function playlists(?string $station = null): array
    {
        return $this->authenticatedClient()->get('/api/station/'.$this->controlStationId($station).'/playlists')->throw()->json();
    }

    public function listeners(?string $station = null): array
    {
        return $this->authenticatedClient()->get('/api/station/'.$this->controlStationId($station).'/listeners')->throw()->json();
    }

    public function report(string $report, array $query = []): array
    {
        $allowed = ['charts', 'by-country', 'by-client', 'by-browser', 'by-stream', 'by-listening-time'];

        if (! in_array($report, $allowed, true)) {
            throw new RuntimeException('Unsupported AzuraCast report.');
        }

        return $this->authenticatedClient()
            ->get('/api/station/'.$this->controlStationId().'/reports/overview/'.$report, $query)
            ->throw()->json();
    }

    public function status(?string $station = null): array
    {
        return $this->client()->get('/api/station/'.$this->controlStationId($station).'/status')->throw()->json();
    }

    public function restart(): array
    {
        return $this->authenticatedClient()->post('/api/station/'.$this->controlStationId().'/restart')->throw()->json() ?? [];
    }

    public function control(string $service, string $action): array
    {
        if (! in_array($service, ['frontend', 'backend'], true) || ! in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new RuntimeException('Unsupported AzuraCast service command.');
        }

        return $this->authenticatedClient()
            ->post('/api/station/'.$this->controlStationId()."/{$service}/{$action}")
            ->throw()
            ->json() ?? [];
    }

    public function requests(): array
    {
        return $this->client()->get('/api/station/'.$this->controlStationId().'/requests')->throw()->json();
    }

    public function submitRequest(string $requestId): array
    {
        return $this->authenticatedClient()
            ->post('/api/station/'.$this->controlStationId().'/request/'.rawurlencode($requestId))
            ->throw()
            ->json() ?? [];
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.azuracast.url'), '/'))
            ->acceptJson()->timeout((int) config('services.azuracast.timeout', 5));

        if (filled(config('services.azuracast.api_key'))) {
            $request->withHeader('X-API-Key', config('services.azuracast.api_key'));
        }

        return $request;
    }

    private function authenticatedClient(): PendingRequest
    {
        $this->ensureConfigured();

        if (blank(config('services.azuracast.api_key'))) {
            throw new RuntimeException('An AzuraCast API key is required for station controls.');
        }

        return $this->client();
    }

    private function stationIdentifier(?string $override = null): string
    {
        return (string) ($override ?: session('radio.active_station') ?: config('services.azuracast.station_id', 'azuratest'));
    }

    private function controlStationId(?string $override = null): int
    {
        $identifier = $this->stationIdentifier($override);

        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        $stationId = data_get($this->nowPlaying($override), 'station.id');

        if (! is_numeric($stationId)) {
            throw new RuntimeException("AzuraCast station '{$identifier}' could not be resolved.");
        }

        return (int) $stationId;
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('AzuraCast is not configured. Set AZURACAST_URL first.');
        }
    }
}
