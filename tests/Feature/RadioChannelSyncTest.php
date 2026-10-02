<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Src\RadioSceaper\Models\RadioSceaperStation;
use Src\RadioSceaper\Services\RadioChannelSync;
use Tests\TestCase;

class RadioChannelSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStations(array $stations): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response($stations),
        ]);
    }

    public function test_it_creates_an_unpublished_record_for_an_unmatched_station(): void
    {
        $this->fakeStations([
            [
                'stationuuid' => 'aaa',
                'name' => 'Pulse FM',
                'url_resolved' => 'https://stream.example.com/pulse',
                'bitrate' => 128,
                'votes' => 10,
                'tags' => 'pop',
                'lastcheckok' => 1,
            ],
        ]);

        $result = app(RadioChannelSync::class)->sync('KH');

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['updated']);

        $channel = RadioSceaperStation::first();
        $this->assertSame('Pulse FM', $channel->name);
        $this->assertFalse($channel->is_published);
        $this->assertSame('aaa', $channel->radio_browser_uuid);
        $this->assertSame('https://stream.example.com/pulse', $channel->stream_url);
    }

    public function test_it_updates_the_stream_url_of_a_matched_record(): void
    {
        $channel = RadioSceaperStation::create([
            'name' => 'Pulse FM',
            'stream_url' => 'https://old.example.com/pulse',
            'radio_browser_uuid' => 'aaa',
            'votes' => 3,
            'is_published' => true,
        ]);

        $this->fakeStations([
            [
                'stationuuid' => 'aaa',
                'name' => 'Pulse FM',
                'url_resolved' => 'https://new.example.com/pulse',
                'bitrate' => 128,
                'votes' => 10,
                'tags' => 'pop',
                'lastcheckok' => 1,
            ],
        ]);

        $result = app(RadioChannelSync::class)->sync('KH');

        $this->assertSame(0, $result['added']);
        $this->assertSame(1, $result['updated']);

        $channel->refresh();
        $this->assertSame('https://new.example.com/pulse', $channel->stream_url);
        $this->assertTrue($channel->is_published);
    }

    public function test_it_returns_null_on_request_failure(): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response(null, 500),
        ]);

        $this->assertNull(app(RadioChannelSync::class)->sync('KH'));
        $this->assertSame(0, RadioSceaperStation::count());
    }
}
