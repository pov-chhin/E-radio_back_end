<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Src\RadioSceaper\RadioSceaper;
use Tests\TestCase;

class RadioSceaperTest extends TestCase
{
    public function test_it_filters_sorts_and_maps_working_stations(): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response([
                [
                    'stationuuid' => 'aaa',
                    'name' => 'Low Votes FM',
                    'url_resolved' => 'https://stream.example.com/low',
                    'bitrate' => 128,
                    'votes' => 5,
                    'tags' => 'pop',
                    'lastcheckok' => 1,
                ],
                [
                    'stationuuid' => 'bbb',
                    'name' => 'High Votes FM',
                    'url_resolved' => 'https://stream.example.com/high',
                    'bitrate' => 320,
                    'votes' => 50,
                    'tags' => 'news',
                    'lastcheckok' => 1,
                ],
                [
                    'stationuuid' => 'ccc',
                    'name' => 'Dead Stream FM',
                    'url_resolved' => 'https://stream.example.com/dead',
                    'bitrate' => 128,
                    'votes' => 999,
                    'tags' => '',
                    'lastcheckok' => 0,
                ],
                [
                    'stationuuid' => 'ddd',
                    'name' => 'No URL FM',
                    'url_resolved' => '',
                    'bitrate' => 128,
                    'votes' => 999,
                    'tags' => '',
                    'lastcheckok' => 1,
                ],
            ]),
        ]);

        $stations = (new RadioSceaper)->get('KH');

        $this->assertSame(['High Votes FM', 'Low Votes FM'], array_column($stations, 'name'));
        $this->assertSame('https://stream.example.com/high', $stations[0]['stream_url']);
        $this->assertSame('bbb', $stations[0]['uuid']);
    }

    public function test_it_returns_null_on_request_failure(): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response(null, 500),
        ]);

        $this->assertNull((new RadioSceaper)->get('KH'));
    }

    public function test_configured_reflects_radio_browser_config(): void
    {
        config(['services.radio_browser.enabled' => true]);
        $this->assertTrue((new RadioSceaper)->configured());

        config(['services.radio_browser.enabled' => false]);
        $this->assertFalse((new RadioSceaper)->configured());
    }
}
