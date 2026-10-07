<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Setting\Models\LanguageSetting;
use Src\User\User;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** The admin panel is shown in English or Khmer: a site default in Settings → Language, and each admin's own choice on top. */
class LanguageSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const KHMER_SETTINGS = 'ការកំណត់';

    private const KHMER_ADMIN_USERS = 'អ្នកប្រើប្រាស់គ្រប់គ្រង';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = $this->createAdmin(['manage-settings', 'view-users', 'create-users']);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_the_migration_seeds_english_as_the_default(): void
    {
        $this->assertSame('en', LanguageSetting::firstOrFail()->locale);
        $this->assertSame('en', LanguageSetting::defaultLocale());
    }

    public function test_the_settings_page_has_a_language_tab_with_english_and_khmer(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('data-settings-tab="language"', false)
            ->assertSee('Language Settings')
            ->assertSee('English')
            ->assertSee('ខ្មែរ')
            ->assertSee('lang="en"', false)
            ->assertDontSee(self::KHMER_SETTINGS);
    }

    public function test_saving_khmer_as_the_site_default_switches_the_panel_to_khmer(): void
    {
        $this->put('/admin/settings/language', ['locale' => 'km'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('settings_tab', 'language');

        $this->assertSame('km', LanguageSetting::firstOrFail()->locale);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('lang="km"', false)
            ->assertSee(self::KHMER_SETTINGS)
            // Khmer OS Battambang first; Noto Sans Khmer is loaded to stand in where it isn't installed.
            ->assertSee('Noto Sans Khmer', false);
        $this->get('/admin/users')->assertOk()->assertSee(self::KHMER_ADMIN_USERS);
    }

    public function test_the_khmer_panel_uses_khmer_os_battambang_and_english_does_not(): void
    {
        $this->put('/admin/settings/language', ['locale' => 'km'])->assertRedirect();

        $khmer = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/html\[lang="km"\],[^{]*\{\s*font-family: "Khmer OS Battambang", "Noto Sans Khmer"/', $khmer);
        // Everywhere that sets a font of its own: the dashboard, the map tooltips and the description editor.
        foreach (['html[lang="km"] .dash', 'html[lang="km"] .jvm-tooltip', 'html[lang="km"] .ql-container', 'html[lang="km"] .ql-toolbar'] as $selector) {
            $this->assertStringContainsString($selector, $khmer);
        }

        // The language switch in the account menu gives the same page in English with none of it.
        $this->put('/admin/locale', ['locale' => 'en'])->assertRedirect();
        $english = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertStringNotContainsString('Khmer OS Battambang', $english);
        $this->assertStringNotContainsString('Noto Sans Khmer', $english);
    }

    public function test_an_unsupported_language_is_refused(): void
    {
        $this->put('/admin/settings/language', ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->put('/admin/settings/language', [])->assertSessionHasErrors('locale');

        $this->assertSame('en', LanguageSetting::firstOrFail()->locale);
    }

    public function test_changing_the_site_default_needs_the_manage_settings_permission(): void
    {
        // No roles at all (createAdmin() would otherwise reuse the shared test role that already holds manage-settings).
        $this->actingAs($this->createAdmin([]), 'admin');

        $this->put('/admin/settings/language', ['locale' => 'km'])->assertForbidden();

        $this->assertSame('en', LanguageSetting::firstOrFail()->locale);
    }

    public function test_any_admin_can_pick_their_own_language_from_the_account_menu(): void
    {
        // An admin with no permissions at all: the switch is theirs to use, it needs none.
        $nobody = $this->createAdmin([]);
        $this->actingAs($nobody, 'admin');

        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('km', $nobody->fresh()->locale);
        // The site default is untouched: this is only their own choice.
        $this->assertSame('en', LanguageSetting::firstOrFail()->locale);
        $this->assertNull($this->admin->fresh()->locale);
    }

    public function test_the_account_menu_offers_both_languages(): void
    {
        $this->get('/admin/users')
            ->assertSee(route('admin.locale.update'), false)
            ->assertSee('value="en"', false)
            ->assertSee('value="km"', false);
    }

    public function test_an_unsupported_personal_language_is_refused(): void
    {
        $this->put('/admin/locale', ['locale' => 'de'])->assertSessionHasErrors('locale');

        $this->assertNull($this->admin->fresh()->locale);
    }

    public function test_an_admins_own_choice_beats_the_site_default_both_ways(): void
    {
        LanguageSetting::firstOrFail()->update(['locale' => 'km']);

        // No personal choice: follows the site default.
        $this->get('/admin/users')->assertSee(self::KHMER_ADMIN_USERS);

        // Chose English while the site default is Khmer: stays English.
        $this->admin->forceFill(['locale' => 'en'])->save();
        $this->get('/admin/users')->assertSee('Admin Users')->assertDontSee(self::KHMER_ADMIN_USERS)->assertSee('lang="en"', false);

        // Chose Khmer while the site default is English.
        LanguageSetting::firstOrFail()->update(['locale' => 'en']);
        $this->admin->forceFill(['locale' => 'km'])->save();
        $this->get('/admin/users')->assertSee(self::KHMER_ADMIN_USERS);
    }

    public function test_a_stale_saved_language_falls_back_to_the_site_default(): void
    {
        $this->admin->forceFill(['locale' => 'xx'])->save();

        $this->get('/admin/users')->assertOk()->assertSee('Admin Users')->assertSee('lang="en"', false);
    }

    public function test_registering_a_user_can_set_their_language(): void
    {
        $this->post('/admin/users', ['name' => 'Sokha', 'email' => 'sokha@example.com', 'password' => 'secret-pass', 'locale' => 'km'])
            ->assertRedirect(route('admin.users.index'));
        $this->post('/admin/users', ['name' => 'Dara', 'email' => 'dara@example.com', 'password' => 'secret-pass', 'locale' => ''])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('km', User::where('email', 'sokha@example.com')->firstOrFail()->locale);
        $this->assertNull(User::where('email', 'dara@example.com')->firstOrFail()->locale);

        $this->post('/admin/users', ['name' => 'Bad', 'email' => 'bad@example.com', 'password' => 'secret-pass', 'locale' => 'zz'])
            ->assertSessionHasErrors('locale');
    }

    public function test_the_register_form_lets_the_admin_choose_a_language(): void
    {
        $this->get('/admin/users/create')
            ->assertOk()
            ->assertSee('name="locale"', false)
            ->assertSee('Site default')
            ->assertSee('ខ្មែរ (KH)');
    }

    public function test_the_language_does_not_leak_into_the_next_request(): void
    {
        $this->put('/admin/locale', ['locale' => 'km']);
        $this->get('/admin/users')->assertSee(self::KHMER_ADMIN_USERS);

        $this->admin->forceFill(['locale' => null])->save();
        $this->get('/admin/users')->assertSee('Admin Users')->assertDontSee(self::KHMER_ADMIN_USERS);
    }
}
