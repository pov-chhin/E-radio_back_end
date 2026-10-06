<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Src\Setting\Models\RustfsSetting;
use Src\Setting\Models\StorageSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class RustfsSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    /** What the tab's form posts once the admin has added the keys to the pre-filled address. */
    private const FORM = [
        'rustfs_key' => 'RUSTFSKEY123',
        'rustfs_secret' => 'rustfs-secret-value',
        'rustfs_bucket' => 'rustfs',
        'rustfs_region' => 'us-east-1',
        'rustfs_endpoint' => 'http://s3.devams.site',
        'rustfs_url' => '',
        'rustfs_use_path_style_endpoint' => '1',
    ];

    private const FOLDERS = ['image_directory' => 'media-contents/thumbnails', 'audio_directory' => 'media-contents/streams'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    /** Defines the RustFS disk for the keys in FORM and swaps in an in-memory double, so "connecting" never leaves the test. */
    private function fakeRustfs(): void
    {
        RustfsSetting::firstOrFail()->fill(['s3_key' => self::FORM['rustfs_key'], 's3_secret' => self::FORM['rustfs_secret']])->applyToConfig();

        Storage::fake('media_rustfs');
    }

    private function connectRustfs(): void
    {
        $this->fakeRustfs();
        $this->put('/admin/settings/rustfs', self::FORM)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_the_settings_page_has_a_rustfs_tab_prefilled_with_the_servers_address(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('data-settings-tab="rustfs"', false)
            ->assertSee('RustFS Settings')
            ->assertSee('value="http://s3.devams.site"', false)
            ->assertSee('value="rustfs"', false)
            ->assertSee('Not set up yet');
    }

    public function test_the_rustfs_storage_type_cannot_be_picked_until_the_connection_is_set_up(): void
    {
        $html = $this->get('/admin/settings')->assertOk()->assertSee('Set it up in the RustFS tab first.')->getContent();
        $this->assertMatchesRegularExpression('/value="rustfs"[^>]*\sdisabled/', $html);

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'rustfs'])
            ->assertSessionHasErrors(['driver' => 'Set up the RustFS connection in the RustFS tab first.']);
        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_saving_the_connection_stores_the_keys_encrypted_and_defines_the_disk(): void
    {
        $this->fakeRustfs();

        $this->put('/admin/settings/rustfs', self::FORM)
            ->assertRedirect()
            ->assertSessionHas('status', 'RustFS connection saved — connected to the bucket.')
            ->assertSessionHas('settings_tab', 'rustfs');

        $setting = RustfsSetting::firstOrFail();
        $this->assertSame('RUSTFSKEY123', $setting->s3_key);
        $this->assertSame('rustfs-secret-value', $setting->s3_secret);
        $this->assertSame('http://s3.devams.site', $setting->s3_endpoint);
        $this->assertTrue($setting->s3_use_path_style_endpoint);

        $row = DB::table('rustfs_settings')->first();
        $this->assertStringNotContainsString('rustfs-secret-value', $row->s3_secret);
        $this->assertStringNotContainsString('RUSTFSKEY123', $row->s3_key);

        $this->assertSame('rustfs', config('filesystems.disks.media_rustfs.bucket'));
        $this->assertSame('http://s3.devams.site', config('filesystems.disks.media_rustfs.endpoint'));
        $this->assertTrue(config('filesystems.disks.media_rustfs.use_path_style_endpoint'));
        // The connection test cleaned up after itself, and saving alone does not change where uploads go.
        $this->assertSame([], Storage::disk('media_rustfs')->allFiles());
        $this->assertSame('public', config('services.media_storage.disk'));
    }

    public function test_the_secret_is_never_sent_back_and_the_tab_shows_the_connection_is_saved(): void
    {
        $this->connectRustfs();

        $this->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('rustfs-secret-value')
            ->assertSee('RUSTFSKEY123')
            ->assertSee('Saved — leave blank to keep it')
            ->assertSee('Connection saved')
            ->assertDontSee('Set it up in the RustFS tab first.');
    }

    public function test_every_connection_field_is_required_the_first_time(): void
    {
        $this->put('/admin/settings/rustfs', ['rustfs_bucket' => '', 'rustfs_region' => '', 'rustfs_endpoint' => ''])
            ->assertSessionHasErrors(['rustfs_key', 'rustfs_secret', 'rustfs_bucket', 'rustfs_region', 'rustfs_endpoint']);

        $this->assertNull(RustfsSetting::firstOrFail()->s3_secret);
    }

    /** Makes the environment supply the keys, as AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY in .env do. */
    private function keysFromEnv(): void
    {
        config(['services.rustfs.key' => 'ENVKEY123', 'services.rustfs.secret' => 'env-secret-value']);
        RustfsSetting::firstOrFail()->applyToConfig();
        Storage::fake('media_rustfs');
    }

    public function test_the_keys_come_from_env_when_none_are_saved_so_the_form_can_leave_them_blank(): void
    {
        $this->keysFromEnv();

        $this->assertSame('ENVKEY123', config('filesystems.disks.media_rustfs.key'));
        $this->assertSame('env-secret-value', config('filesystems.disks.media_rustfs.secret'));

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Using the keys from .env')
            ->assertSee('From .env — leave blank to use it')
            ->assertDontSee('env-secret-value')
            ->assertDontSee('ENVKEY123')
            ->assertDontSee('Set it up in the RustFS tab first.');

        // Changing the bucket rebuilds the disk, so fake the rebuilt one before saving.
        RustfsSetting::firstOrFail()->fill(['s3_bucket' => 'e-radio-media'])->applyToConfig();
        Storage::fake('media_rustfs');

        $this->put('/admin/settings/rustfs', [...self::FORM, 'rustfs_key' => '', 'rustfs_secret' => '', 'rustfs_bucket' => 'e-radio-media'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'RustFS connection saved — connected to the bucket.');

        // Nothing secret was copied into the database; the bucket was.
        $row = DB::table('rustfs_settings')->first();
        $this->assertNull($row->s3_key);
        $this->assertNull($row->s3_secret);
        $this->assertSame('e-radio-media', $row->s3_bucket);
    }

    public function test_keys_saved_in_the_form_win_over_the_ones_in_env(): void
    {
        $this->keysFromEnv();
        $this->fakeRustfs();

        $this->put('/admin/settings/rustfs', self::FORM)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('RUSTFSKEY123', config('filesystems.disks.media_rustfs.key'));
        $this->assertSame('rustfs-secret-value', config('filesystems.disks.media_rustfs.secret'));
        $this->get('/admin/settings')->assertOk()->assertSee('Connection saved')->assertDontSee('Using the keys from .env');
    }

    public function test_a_key_typed_in_next_to_env_keys_needs_its_secret(): void
    {
        $this->keysFromEnv();

        $this->put('/admin/settings/rustfs', [...self::FORM, 'rustfs_secret' => ''])->assertSessionHasErrors('rustfs_secret');
        $this->put('/admin/settings/rustfs', [...self::FORM, 'rustfs_key' => ''])->assertSessionHasErrors('rustfs_key');

        $this->assertNull(DB::table('rustfs_settings')->value('s3_key'));
    }

    public function test_the_endpoint_must_be_a_url(): void
    {
        $this->put('/admin/settings/rustfs', [...self::FORM, 'rustfs_endpoint' => 's3.devams.site'])
            ->assertSessionHasErrors('rustfs_endpoint');
    }

    public function test_leaving_the_key_and_secret_blank_keeps_the_saved_ones(): void
    {
        $this->connectRustfs();

        // Changing any connection field rebuilds the disk, so fake the rebuilt one before saving.
        RustfsSetting::firstOrFail()->fill(['s3_url' => 'http://s3.devams.site/rustfs'])->applyToConfig();
        Storage::fake('media_rustfs');

        $this->put('/admin/settings/rustfs', [...self::FORM, 'rustfs_key' => '', 'rustfs_secret' => '', 'rustfs_url' => 'http://s3.devams.site/rustfs'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = RustfsSetting::firstOrFail();
        $this->assertSame('RUSTFSKEY123', $setting->s3_key);
        $this->assertSame('rustfs-secret-value', $setting->s3_secret);
        $this->assertSame('http://s3.devams.site/rustfs', $setting->s3_url);
    }

    public function test_a_connection_that_cannot_be_written_to_is_refused_and_nothing_is_saved(): void
    {
        $this->fakeRustfs();
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException('SignatureDoesNotMatch'));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_rustfs', $disk);

        $this->put('/admin/settings/rustfs', self::FORM)->assertRedirect()->assertSessionHasErrors('rustfs_connection');

        $this->assertStringContainsString('SignatureDoesNotMatch', session('errors')->first('rustfs_connection'));
        $this->assertNull(RustfsSetting::firstOrFail()->s3_secret);
        $this->assertNull(session()->getOldInput('rustfs_secret'));
        $this->assertSame('RUSTFSKEY123', session()->getOldInput('rustfs_key'));
    }

    public function test_once_set_up_rustfs_can_be_chosen_as_the_storage_type_and_uploads_follow_it(): void
    {
        $this->connectRustfs();

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'rustfs'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('settings_tab', 'storage');

        $this->assertSame('rustfs', StorageSetting::firstOrFail()->driver);
        $this->assertSame('media_rustfs', config('services.media_storage.disk'));
        $this->assertSame([], Storage::disk('media_rustfs')->allFiles());

        $html = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="rustfs"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="rustfs"[^>]*\sdisabled/', $html);
    }

    public function test_choosing_rustfs_when_it_cannot_be_written_to_is_refused(): void
    {
        $this->connectRustfs();
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException('Access Denied'));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_rustfs', $disk);

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'rustfs'])->assertSessionHasErrors('storage_connection');

        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_the_rustfs_tab_and_the_s3_form_do_not_share_input_or_errors(): void
    {
        // A failed RustFS save must not pre-fill (or flag) the S3 fields on the Storage tab.
        $this->put('/admin/settings/rustfs', [...Arr::except(self::FORM, ['rustfs_key']), 'rustfs_key' => '', 'rustfs_bucket' => 'wrong bucket!'])
            ->assertSessionHasErrors(['rustfs_bucket', 'rustfs_key']);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSessionMissing('errors.s3_bucket');
        $this->assertNull(session()->getOldInput('s3_bucket'));
    }

    public function test_a_stale_row_cannot_leave_rustfs_selected_when_its_credentials_are_unreadable(): void
    {
        DB::table('rustfs_settings')->update(['s3_key' => 'not-encrypted', 's3_secret' => 'not-encrypted']);
        DB::table('storage_settings')->update(['driver' => 'rustfs']);

        StorageSetting::firstOrFail()->applyToConfig();

        // Uploads fall back to local rather than failing, and the page says what is wrong.
        $this->assertSame('public', config('services.media_storage.disk'));
        $this->get('/admin/settings')->assertOk()->assertSee('The saved RustFS credentials can no longer be read');
    }
}
