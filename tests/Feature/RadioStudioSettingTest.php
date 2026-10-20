<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Src\Setting\Models\RadioStudioSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** The sidebar's "Radio Studio" link is a link out to the studio, below Radio Channels; its address is edited under Settings. */
class RadioStudioSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const STUDIO = 'http://studio.devams.site/dashboard';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signIn(array $permissions = ['manage-settings', 'view-radio-channels', 'view-programs']): void
    {
        $this->actingAs($this->createAdmin($permissions), 'admin');
    }

    private function sidebar(): string
    {
        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        return substr($html, $start = strpos($html, 'main-nav'), strpos($html, '</nav>', $start) - $start);
    }

    private function studioLink(string $url): string
    {
        return '<a href="'.$url.'" target="_blank" rel="noopener">';
    }

    public function test_the_migration_seeds_the_current_studio_address(): void
    {
        $this->assertSame(1, RadioStudioSetting::count());
        $this->assertSame(self::STUDIO, RadioStudioSetting::firstOrFail()->url);
        $this->assertSame(self::STUDIO, RadioStudioSetting::studioUrl());
    }

    public function test_the_sidebar_item_is_radio_studio_right_below_radio_channels(): void
    {
        $this->signIn();
        $nav = $this->sidebar();

        $this->assertMatchesRegularExpression(
            '/'.preg_quote($this->studioLink(self::STUDIO), '/').'\s*<span class="nav-icon fa fa-headphones"><\/span><span>Radio Studio<\/span>/',
            $nav,
        );
        $this->assertStringNotContainsString('Radio Station', $nav);

        // Straight after Radio Channels, and both in the Content group, below Podcasts Program.
        $channels = strpos($nav, '<span>Radio Channels</span>');
        $studio = strpos($nav, '<span>Radio Studio</span>');
        $this->assertNotFalse($channels);
        $this->assertGreaterThan($channels, $studio);
        $this->assertGreaterThan(strpos($nav, '<span>Podcasts Program</span>'), $channels);
        $this->assertGreaterThan(strpos($nav, '<p class="nav-section">Content</p>'), $channels);
        $this->assertStringStartsWith($this->studioLink(self::STUDIO), ltrim(substr($nav, strpos($nav, '</a>', $channels) + 4)));
        // A way out of the panel, so never the page you are on.
        $this->assertDoesNotMatchRegularExpression('/<a href="'.preg_quote(self::STUDIO, '/').'"[^>]*class="active"/', $nav);
    }

    public function test_the_address_is_edited_under_settings_and_the_sidebar_follows_it(): void
    {
        $this->signIn();

        // A Radio Studio tab with the current address in it.
        $page = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertStringContainsString('data-settings-tab="radio-studio"', $page);
        $this->assertMatchesRegularExpression('/data-settings-tab="radio-studio">Radio Studio<\/button>/', $page);
        $this->assertMatchesRegularExpression('/<input id="radio_studio_url" name="url" type="url"[^>]*value="'.preg_quote(self::STUDIO, '/').'"/', $page);
        $this->assertStringContainsString('action="'.e(route('admin.settings.radio-studio.update')).'"', $page);

        $this->put('/admin/settings/radio-studio', ['url' => 'https://studio.example.com/live'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Radio Studio settings saved.')
            ->assertSessionHas('settings_tab', 'radio-studio');

        $this->assertSame('https://studio.example.com/live', RadioStudioSetting::firstOrFail()->url);
        $this->assertSame(1, RadioStudioSetting::count());

        // The sidebar link opens the new address, and the old one is gone.
        $nav = $this->sidebar();
        $this->assertStringContainsString($this->studioLink('https://studio.example.com/live'), $nav);
        $this->assertStringNotContainsString('studio.devams.site', $nav);
        // The tab that was saved stays the open one.
        $this->assertMatchesRegularExpression('/data-settings-panel="radio-studio"\s*>/', $this->withSession(['settings_tab' => 'radio-studio'])->get('/admin/settings')->getContent());
    }

    public function test_an_empty_address_hides_the_link(): void
    {
        $this->signIn();

        $this->put('/admin/settings/radio-studio', ['url' => ''])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(RadioStudioSetting::firstOrFail()->url);
        $this->assertNull(RadioStudioSetting::studioUrl());
        $nav = $this->sidebar();
        $this->assertStringNotContainsString('Radio Studio', $nav);
        $this->assertStringNotContainsString('studio.devams.site', $nav);
        // Radio Channels is still there on its own.
        $this->assertStringContainsString('<span>Radio Channels</span>', $nav);

        // Typing one back in brings the link back.
        $this->put('/admin/settings/radio-studio', ['url' => 'http://studio.devams.site/dashboard'])->assertSessionHasNoErrors();
        $this->assertStringContainsString($this->studioLink(self::STUDIO), $this->sidebar());
    }

    public function test_only_web_addresses_are_accepted(): void
    {
        $this->signIn();

        foreach (['javascript:alert(1)', 'ftp://studio.example.com/file', 'studio.example.com', 'not a url', 'https://'.str_repeat('a', 2050).'.com'] as $bad) {
            $this->from('/admin/settings')
                ->put('/admin/settings/radio-studio', ['url' => $bad])
                ->assertRedirect('/admin/settings')
                ->assertSessionHasErrors('url');
            $this->assertSame(self::STUDIO, RadioStudioSetting::firstOrFail()->url, $bad);
        }

        // The message names the field, and the failed tab is the one shown.
        $this->from('/admin/settings')->put('/admin/settings/radio-studio', ['url' => 'javascript:alert(1)']);
        $page = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertStringContainsString('The Radio Studio address field must be a valid URL.', $page);
        $this->assertMatchesRegularExpression('/data-settings-panel="radio-studio"\s*>/', $page);
        $this->assertStringNotContainsString('javascript:alert', $this->sidebar());
        // http and https both pass.
        foreach (['http://studio.example.com', 'https://studio.example.com:8443/a/b?c=d'] as $good) {
            $this->put('/admin/settings/radio-studio', ['url' => $good])->assertSessionHasNoErrors();
            $this->assertSame($good, RadioStudioSetting::firstOrFail()->url);
        }
    }

    public function test_changing_the_address_needs_the_manage_settings_permission(): void
    {
        $this->signIn(['view-radio-channels', 'view-programs']);

        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings/radio-studio', ['url' => 'https://studio.example.com'])->assertForbidden();
        $this->assertSame(self::STUDIO, RadioStudioSetting::firstOrFail()->url);
    }

    public function test_the_link_is_left_off_for_an_admin_who_cannot_see_radio_channels(): void
    {
        $this->signIn(['view-programs']);

        $this->assertStringNotContainsString('Radio Studio', $this->sidebar());
    }

    public function test_the_sidebar_still_works_before_the_table_exists(): void
    {
        $this->signIn();
        Schema::drop('radio_studio_settings');

        // Not migrated yet: the link uses the default address instead of breaking every page.
        $this->assertSame(self::STUDIO, RadioStudioSetting::studioUrl());
        $this->assertStringContainsString($this->studioLink(self::STUDIO), $this->sidebar());
    }

    public function test_radio_studio_is_translated_into_khmer(): void
    {
        $this->signIn();
        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect();

        $this->assertStringContainsString('<span>ស្ទូឌីយោវិទ្យុ</span>', $this->sidebar());
        $settings = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertStringContainsString('data-settings-tab="radio-studio">ស្ទូឌីយោវិទ្យុ</button>', $settings);
        $this->assertStringContainsString('ការកំណត់ស្ទូឌីយោវិទ្យុ', $settings);
    }
}
