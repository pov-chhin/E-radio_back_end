<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Src\User\User;
use Src\UserManagement\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // No permissions at all: the profile is for every signed-in admin.
        $this->admin = $this->createAdmin([]);
        $this->admin->update(['name' => 'Sokha', 'email' => 'sokha@example.com', 'password' => 'old-password']);
        // Read back what a signed-in admin really is: loaded from the database, defaults (is_active) included.
        $this->admin->refresh();
        $this->actingAs($this->admin, 'admin');
    }

    /** The smallest valid submission: what the form posts with the password fields left empty. */
    private function details(array $overrides = []): array
    {
        return ['name' => 'Sokha', 'email' => 'sokha@example.com', 'locale' => '', ...$overrides];
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        auth('admin')->logout();

        $this->get('/admin/profile')->assertRedirect(route('login'));
    }

    public function test_the_account_menu_links_to_the_profile_page(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('href="'.route('admin.profile.show').'"', false)
            ->assertSee('My Profile');
    }

    public function test_any_admin_can_open_their_profile_and_sees_their_details(): void
    {
        $this->admin->roles()->attach(Role::create(['name' => 'Editor', 'slug' => 'editor']));
        $this->admin->update(['locale' => 'en']);

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSeeInOrder(['Name', 'Sokha', 'Email', 'sokha@example.com', 'Roles', 'Editor', 'Language', 'English (EN)', 'Status', 'Active', 'Member since'])
            ->assertSee('href="'.route('admin.profile.edit').'"', false)
            ->assertSee('Edit Profile')
            // Passwords are changed on the edit page, so the detail page has no button for it.
            ->assertDontSee('Change password');
    }

    public function test_the_profile_shows_initials_without_a_photo_and_the_photo_when_there_is_one(): void
    {
        $html = $this->get('/admin/profile')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span class="profile-avatar"[^>]*>\s*SO\s*</span>#', $html);

        $this->admin->forceFill(['avatar_url' => 'https://example.com/me.jpg'])->save();

        $html = $this->get('/admin/profile')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span class="profile-avatar"[^>]*>\s*<img src="https://example.com/me.jpg" alt="">#', $html);
    }

    public function test_the_top_bar_has_no_view_site_button(): void
    {
        $this->get('/admin')->assertOk()->assertDontSee('View site');
    }

    public function test_the_profile_says_when_the_language_is_the_site_default_and_when_the_account_is_inactive(): void
    {
        $this->admin->forceFill(['locale' => null, 'is_active' => false])->save();

        $html = $this->get('/admin/profile')->assertOk()->assertSee('Site default')->getContent();

        $this->assertStringContainsString('<span class="badge slate">Inactive</span>', $html);
    }

    public function test_the_edit_page_holds_the_form_with_the_current_values(): void
    {
        $this->get('/admin/profile/edit')
            ->assertOk()
            ->assertSee('Edit Profile')
            ->assertSee('value="Sokha"', false)
            ->assertSee('value="sokha@example.com"', false)
            ->assertSee('id="change-password"', false)
            ->assertSee('href="'.route('admin.profile.show').'"', false);
    }

    public function test_it_updates_the_name_email_and_language(): void
    {
        $this->put('/admin/profile', $this->details(['name' => 'Sokha Chan', 'email' => 'chan@example.com', 'locale' => 'km']))
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status', 'Profile updated.');

        $admin = $this->admin->fresh();
        $this->assertSame('Sokha Chan', $admin->name);
        $this->assertSame('chan@example.com', $admin->email);
        $this->assertSame('km', $admin->locale);
        $this->assertTrue(Hash::check('old-password', $admin->password));
    }

    public function test_it_saves_the_first_and_last_name_phone_number_and_address(): void
    {
        $this->put('/admin/profile', $this->details([
            'first_name' => 'Sokha',
            'last_name' => 'Chan',
            'phone_number' => '+855 12 345 678',
            'address' => 'Phnom Penh, Cambodia',
        ]))->assertRedirect(route('admin.profile.show'))->assertSessionHasNoErrors();

        $admin = $this->admin->fresh();
        $this->assertSame('Sokha', $admin->first_name);
        $this->assertSame('Chan', $admin->last_name);
        $this->assertSame('+855 12 345 678', $admin->phone_number);
        $this->assertSame('Phnom Penh, Cambodia', $admin->address);

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSeeInOrder(['First name', 'Sokha', 'Last name', 'Chan', 'Phone number', '+855 12 345 678', 'Address', 'Phnom Penh, Cambodia']);
    }

    public function test_the_optional_details_can_be_cleared_again(): void
    {
        $this->admin->forceFill(['first_name' => 'Sokha', 'last_name' => 'Chan', 'phone_number' => '012345678', 'address' => 'Phnom Penh'])->save();

        $this->put('/admin/profile', $this->details(['first_name' => '', 'last_name' => '', 'phone_number' => '', 'address' => '']))
            ->assertRedirect(route('admin.profile.show'));

        $admin = $this->admin->fresh();
        $this->assertNull($admin->first_name);
        $this->assertNull($admin->last_name);
        $this->assertNull($admin->phone_number);
        $this->assertNull($admin->address);
    }

    public function test_a_phone_number_with_letters_is_rejected(): void
    {
        $this->put('/admin/profile', $this->details(['phone_number' => 'call me maybe']))->assertSessionHasErrors('phone_number');

        $this->assertNull($this->admin->fresh()->phone_number);
    }

    public function test_the_profile_shows_a_dash_for_details_that_are_not_filled_in(): void
    {
        $html = $this->get('/admin/profile')->assertOk()->getContent();

        foreach (['First name', 'Last name', 'Phone number', 'Address'] as $label) {
            $this->assertMatchesRegularExpression('#<dt>'.$label.'</dt><dd>—</dd>#u', $html, "$label should show a dash");
        }
    }

    public function test_the_edit_page_has_fields_for_the_new_details(): void
    {
        $this->admin->forceFill(['first_name' => 'Sokha', 'phone_number' => '012345678', 'address' => 'Phnom Penh'])->save();

        $this->get('/admin/profile/edit')
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('value="012345678"', false)
            ->assertSee('value="Phnom Penh"', false);
    }

    public function test_the_language_can_go_back_to_the_site_default(): void
    {
        $this->admin->update(['locale' => 'km']);

        $this->put('/admin/profile', $this->details(['locale' => '']))->assertRedirect(route('admin.profile.show'));

        $this->assertNull($this->admin->fresh()->locale);
    }

    public function test_the_email_may_be_kept_but_not_taken_from_someone_else(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password123']);

        $this->put('/admin/profile', $this->details())->assertSessionHasNoErrors();

        $this->put('/admin/profile', $this->details(['email' => $other->email]))->assertSessionHasErrors('email');

        $this->assertSame('sokha@example.com', $this->admin->fresh()->email);
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $this->put('/admin/profile', $this->details(['password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password']))
            ->assertSessionHasErrors('current_password');

        $this->put('/admin/profile', $this->details([
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]))->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $this->admin->fresh()->password));
    }

    public function test_it_changes_the_password_when_the_current_one_is_right(): void
    {
        $this->put('/admin/profile', $this->details([
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]))->assertRedirect(route('admin.profile.show'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $this->admin->fresh()->password));

        // Still signed in afterwards.
        $this->get('/admin/profile')->assertOk();
        $this->get('/admin/profile/edit')->assertOk();
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $this->put('/admin/profile', $this->details([
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'something-else',
        ]))->assertSessionHasErrors('password');

        $this->put('/admin/profile', $this->details([
            'current_password' => 'old-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $this->admin->fresh()->password));
    }

    public function test_it_cannot_be_used_to_change_roles_or_active_status(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        $this->put('/admin/profile', $this->details(['roles' => [$role->id], 'is_active' => 0, 'type' => 'customer']))
            ->assertRedirect(route('admin.profile.show'));

        $admin = $this->admin->fresh();
        $this->assertFalse($admin->roles()->where('roles.id', $role->id)->exists());
        $this->assertTrue($admin->is_active);
        $this->assertSame('admin', $admin->type);
    }

    private function picture(string $name = 'me.png', int $width = 800, int $height = 500): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public function test_it_uploads_a_photo_cropped_to_a_square_webp(): void
    {
        Storage::fake('public');

        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]))
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHasNoErrors();

        $path = $this->admin->fresh()->getRawOriginal('avatar_url');
        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f-]{36}\.webp$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame([300, 300], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }

    public function test_a_new_photo_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]));
        $first = $this->admin->fresh()->getRawOriginal('avatar_url');

        $this->put('/admin/profile', $this->details(['photo' => $this->picture('second.png')]));
        $second = $this->admin->fresh()->getRawOriginal('avatar_url');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_saving_without_a_file_keeps_the_photo(): void
    {
        Storage::fake('public');
        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]));
        $path = $this->admin->fresh()->getRawOriginal('avatar_url');

        $this->put('/admin/profile', $this->details(['name' => 'Sokha Chan']))->assertSessionHasNoErrors();

        $this->assertSame($path, $this->admin->fresh()->getRawOriginal('avatar_url'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]));
        $path = $this->admin->fresh()->getRawOriginal('avatar_url');

        $this->put('/admin/profile', $this->details(['remove_photo' => '1']))->assertSessionHasNoErrors();

        $this->assertNull($this->admin->fresh()->getRawOriginal('avatar_url'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_external_photo_address_is_replaced_by_an_upload(): void
    {
        Storage::fake('public');
        $this->admin->forceFill(['avatar_url' => 'https://example.com/google.jpg'])->save();

        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]))->assertSessionHasNoErrors();

        $this->assertStringStartsWith('avatars/', $this->admin->fresh()->getRawOriginal('avatar_url'));
    }

    public function test_a_file_that_is_not_a_picture_is_rejected_and_the_old_photo_stays(): void
    {
        Storage::fake('public');
        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]));
        $path = $this->admin->fresh()->getRawOriginal('avatar_url');

        $this->put('/admin/profile', $this->details(['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')]))
            ->assertSessionHasErrors('photo');

        $this->assertSame($path, $this->admin->fresh()->getRawOriginal('avatar_url'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_photo_over_five_megabytes_is_rejected(): void
    {
        Storage::fake('public');

        $this->put('/admin/profile', $this->details(['photo' => $this->picture()->size(6000)]))->assertSessionHasErrors('photo');

        $this->assertNull($this->admin->fresh()->getRawOriginal('avatar_url'));
    }

    public function test_a_photo_address_in_the_request_is_ignored_and_nothing_is_downloaded(): void
    {
        Storage::fake('public');
        Http::fake();

        $this->put('/admin/profile', $this->details(['photo_url' => 'http://169.254.169.254/latest/meta-data']))
            ->assertRedirect(route('admin.profile.show'));

        Http::assertNothingSent();
        $this->assertNull($this->admin->fresh()->getRawOriginal('avatar_url'));
    }

    public function test_the_photo_shows_in_the_top_bar_and_on_the_profile(): void
    {
        Storage::fake('public');
        $this->put('/admin/profile', $this->details(['photo' => $this->picture()]));
        $path = $this->admin->fresh()->getRawOriginal('avatar_url');

        $pattern = '#<img src="[^"]*/storage/'.preg_quote($path, '#').'" alt="">#';
        $this->assertMatchesRegularExpression('#<span class="user-avatar">\s*'.substr($pattern, 1, -1).'#', $this->get('/admin')->assertOk()->getContent());
        $this->assertMatchesRegularExpression($pattern, $this->get('/admin/profile')->assertOk()->getContent());
    }

    public function test_the_edit_form_can_send_a_file_and_offers_removal_only_when_there_is_a_photo(): void
    {
        $this->get('/admin/profile/edit')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photo"', false)
            ->assertDontSee('name="remove_photo"', false);

        $this->admin->forceFill(['avatar_url' => 'avatars/me.webp'])->save();

        $this->get('/admin/profile/edit')->assertOk()->assertSee('name="remove_photo"', false);
    }
}
