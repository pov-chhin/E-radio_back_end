<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Src\Setting\Models\RadioScraperSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class RadioScraperSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    public function test_migration_seeds_a_singleton_row_from_env_config(): void
    {
        $this->assertSame(1, RadioScraperSetting::count());
    }

    public function test_edit_page_shows_current_values(): void
    {
        $this->get('/admin/settings')->assertOk()->assertSee('Country code');
    }

    public function test_update_saves_and_applies_to_config(): void
    {
        $this->put('/admin/settings', [
            'enabled' => '1',
            'country' => 'us',
        ])->assertRedirect();

        $setting = RadioScraperSetting::firstOrFail();
        $this->assertTrue($setting->enabled);
        $this->assertSame('US', $setting->country);

        $this->assertTrue(config('services.radio_browser.enabled'));
        $this->assertSame('US', config('services.radio_browser.country'));
    }

    public function test_disabling_removes_scraped_stations_from_the_public_dashboard(): void
    {
        $this->put('/admin/settings', ['country' => 'kh'])->assertRedirect();

        $response = $this->get('/admin/studio');

        $response->assertOk();
        $this->assertArrayNotHasKey('scraped_stations', $response->original->getData());
    }

    public function test_enabling_adds_scraped_stations_to_the_public_dashboard(): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response([]),
        ]);

        $this->put('/admin/settings', ['enabled' => '1', 'country' => 'kh'])->assertRedirect();

        $response = $this->get('/admin/studio');

        $response->assertOk();
        $this->assertArrayHasKey('scraped_stations', $response->original->getData());
    }
}
