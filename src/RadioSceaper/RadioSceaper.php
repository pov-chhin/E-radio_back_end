<?php

namespace Src\RadioSceaper;

use Illuminate\Support\Facades\Http;

class RadioSceaper
{
    private const API = 'https://de1.api.radio-browser.info/json/stations/bycountrycodeexact/';

    public function configured(): bool
    {
        return (bool) config('services.radio_browser.enabled');
    }

    /**
     * Live, working stations for a country from radio-browser.info, sorted
     * by community votes. Only stations that last checked OK are included.
     * Null on request failure.
     */
    public function get(string $countryCode = 'KH'): ?array
    {
        $response = Http::withHeaders(['User-Agent' => config('app.name').'/1.0'])
            ->timeout(10)
            ->get(self::API.strtoupper($countryCode));

        if ($response->failed()) {
            return null;
        }

        return collect($response->json())
            ->filter(fn ($station) => ! empty($station['url_resolved']) && (int) ($station['lastcheckok'] ?? 0) === 1)
            ->sortByDesc('votes')
            ->values()
            ->map(fn ($station) => [
                'uuid' => $station['stationuuid'] ?? null,
                'name' => $station['name'],
                'stream_url' => $station['url_resolved'],
                'bitrate' => $station['bitrate'],
                'votes' => $station['votes'],
                'tags' => $station['tags'],
            ])
            ->all();
    }
}
