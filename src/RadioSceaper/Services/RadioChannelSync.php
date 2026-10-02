<?php

namespace Src\RadioSceaper\Services;

use Illuminate\Support\Collection;
use Src\RadioSceaper\Models\RadioSceaperStation;
use Src\RadioSceaper\RadioSceaper;

class RadioChannelSync
{
    public function __construct(private readonly RadioSceaper $scraper) {}

    /**
     * Checks scraped radio-browser.info stations against existing
     * RadioSceaperStation records: a match has its stream_url refreshed if
     * it changed; a station with no match is added as a new, unpublished
     * record. Never publishes anything and never deletes — review and
     * publish manually.
     *
     * @return array{added: int, updated: int, stations: int, added_channels: array, updated_channels: array}|null
     *               Null if the radio-browser.info request failed.
     */
    public function sync(string $countryCode): ?array
    {
        $stations = $this->scraper->get($countryCode);

        if ($stations === null) {
            return null;
        }

        $channels = RadioSceaperStation::all();
        $added = [];
        $updated = [];

        foreach ($stations as $station) {
            $channel = $this->findMatch($station, $channels);

            if ($channel === null) {
                $channel = RadioSceaperStation::create([
                    'name' => $station['name'],
                    'stream_url' => $station['stream_url'],
                    'bitrate' => $station['bitrate'],
                    'votes' => $station['votes'],
                    'tags' => $station['tags'],
                    'radio_browser_uuid' => $station['uuid'],
                    'is_published' => false,
                ]);
                $channels->push($channel);
                $added[] = ['id' => $channel->id, 'name' => $channel->name, 'stream_url' => $channel->stream_url];

                continue;
            }

            $changes = [];

            if ($channel->stream_url !== $station['stream_url']) {
                $changes['stream_url'] = $station['stream_url'];
            }

            if ($channel->radio_browser_uuid === null && $station['uuid'] !== null) {
                $changes['radio_browser_uuid'] = $station['uuid'];
            }

            if ($channel->votes !== $station['votes']) {
                $changes['votes'] = $station['votes'];
            }

            if ($changes !== []) {
                $channel->update($changes);
            }

            if (isset($changes['stream_url'])) {
                $updated[] = ['id' => $channel->id, 'name' => $channel->name, 'stream_url' => $channel->stream_url];
            }
        }

        return [
            'added' => count($added),
            'updated' => count($updated),
            'stations' => count($stations),
            'added_channels' => $added,
            'updated_channels' => $updated,
        ];
    }

    /**
     * Finds the existing record a radio-browser.info station corresponds
     * to, if any: first by the station's stable radio-browser.info uuid,
     * then by exact (case-insensitive) name, then by the exact stream_url.
     */
    private function findMatch(array $station, Collection $channels): ?RadioSceaperStation
    {
        if ($station['uuid'] !== null) {
            $byUuid = $channels->first(
                fn (RadioSceaperStation $channel) => $channel->radio_browser_uuid === $station['uuid']
            );

            if ($byUuid !== null) {
                return $byUuid;
            }
        }

        $byName = $channels->first(
            fn (RadioSceaperStation $channel) => strcasecmp($channel->name, $station['name']) === 0
        );

        if ($byName !== null) {
            return $byName;
        }

        return $channels->first(
            fn (RadioSceaperStation $channel) => $channel->stream_url === $station['stream_url']
        );
    }
}
