<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class RadioDashboardTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['view-dashboard']), 'admin');
    }

    public function test_dashboard_pages_render(): void
    {
        foreach (['/admin/studio', '/admin/studio/live', '/admin/studio/stations', '/admin/studio/player', '/admin/studio/playlist', '/admin/studio/schedule', '/admin/studio/listeners', '/admin/studio/settings'] as $path) {
            $this->get($path)->assertOk()->assertSee('cms admin');
        }
    }

    public function test_live_source_can_be_toggled(): void
    {
        $this->post('/admin/studio/live/toggle')->assertRedirect();
        $this->assertFalse(session('radio.is_live'));
    }

    public function test_settings_are_validated_and_saved(): void
    {
        $this->post('/admin/studio/settings', [
            'station_name' => 'Pulse FM',
            'station_url' => 'https://radio.example.com',
            'fallback_enabled' => '1',
            'crossfade' => 4,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Pulse FM', session('radio.settings.station_name'));
    }

    public function test_it_controls_azuratest_autodj_through_azuracast(): void
    {
        config([
            'services.azuracast.url' => 'https://azura.example.test',
            'services.azuracast.api_key' => 'test-api-key',
            'services.azuracast.station_id' => 'azuratest',
        ]);

        Http::fake([
            'https://azura.example.test/api/nowplaying/azuratest' => Http::response(['station' => ['id' => 7]]),
            'https://azura.example.test/api/station/7/backend/restart' => Http::response(['success' => true]),
        ]);

        $this->post('/admin/studio/services/backend/restart')
            ->assertRedirect()
            ->assertSessionHas('status', 'AutoDJ restart command sent.');

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://azura.example.test/api/station/7/backend/restart'
            && $request->hasHeader('X-API-Key', 'test-api-key')
        );
    }

    public function test_dashboard_uses_the_connected_azuracast_station_details(): void
    {
        config([
            'services.azuracast.url' => 'https://azura.example.test',
            'services.azuracast.station_id' => 'ams_fm97radio',
        ]);

        Http::fake([
            'https://azura.example.test/api/nowplaying/ams_fm97radio' => Http::response([
                'station' => [
                    'id' => 7,
                    'name' => 'FM97',
                    'listen_url' => 'https://azura.example.test/listen/ams_fm97radio/radio.mp3',
                ],
                'listeners' => ['current' => 12],
                'now_playing' => [
                    'playlist' => 'Khmer Hits',
                    'song' => ['title' => 'Connected Song', 'artist' => 'AMS', 'art' => null],
                ],
                'is_online' => true,
            ]),
            'https://azura.example.test/api/nowplaying' => Http::response([
                [
                    'station' => ['id' => 2, 'name' => 'AzuraTest Radio', 'mounts' => []],
                    'listeners' => ['current' => 3],
                    'now_playing' => ['song' => ['genre' => 'Test']],
                    'live' => ['is_live' => false],
                    'is_online' => true,
                ],
                [
                    'station' => ['id' => 7, 'name' => 'FM97', 'mounts' => []],
                    'listeners' => ['current' => 12],
                    'now_playing' => ['song' => ['genre' => 'Khmer pop']],
                    'live' => ['is_live' => false],
                    'is_online' => true,
                ],
            ]),
            'https://azura.example.test/api/station/7/schedule*' => Http::response([]),
        ]);

        $this->get('/admin/studio/player')
            ->assertOk()
            ->assertSee('FM97')
            ->assertSee('Connected Song')
            ->assertSee('https://azura.example.test/listen/ams_fm97radio/radio.mp3');

        $this->get('/admin/studio')
            ->assertOk()
            ->assertSee('All stations')
            ->assertSee('2 station(s) connected through AzuraCast')
            ->assertSee('AzuraTest Radio')
            ->assertSee('FM97');
    }

    public function test_azuracast_controls_fail_safely_when_not_configured(): void
    {
        config(['services.azuracast.url' => null]);

        $this->post('/admin/studio/station/restart')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Auth::guard('admin')->logout();

        $this->get('/admin/studio')->assertRedirect(route('login'));
    }
}
