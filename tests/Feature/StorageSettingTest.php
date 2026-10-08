<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Src\Setting\Models\StorageSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class StorageSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const S3_FORM = [
        'driver' => 's3',
        'image_directory' => 'media-contents/thumbnails',
        'audio_directory' => 'media-contents/streams',
        's3_key' => 'AKIATESTKEY123',
        's3_secret' => 'super-secret-value',
        's3_bucket' => 'radio-media',
        's3_region' => 'auto',
        's3_endpoint' => 'https://acct.r2.cloudflarestorage.com',
        's3_url' => 'https://cdn.example.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    /**
     * Defines the S3 disk for $form's credentials and swaps in an in-memory double for it, so saving the
     * settings can "connect" without a request leaving the test. The disk is built first because applying
     * settings rebuilds it, and the controller's own apply is then a no-op for the same credentials.
     */
    private function fakeS3(array $form = self::S3_FORM): void
    {
        StorageSetting::firstOrFail()
            ->fill(Arr::only($form, ['s3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url']))
            ->applyToConfig();

        Storage::fake('media_s3');
    }

    public function test_migration_seeds_a_singleton_row_from_config_defaults(): void
    {
        $this->assertSame(1, StorageSetting::count());
        $setting = StorageSetting::firstOrFail();
        $this->assertSame('media-contents/thumbnails', $setting->image_directory);
        $this->assertSame('media-contents/streams', $setting->audio_directory);
        $this->assertSame('local', $setting->driver);
    }

    public function test_edit_page_shows_both_tabs(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Radio Scraper')
            ->assertSee('Storage')
            ->assertSee('Image folder')
            ->assertSee('Audio folder');
    }

    public function test_youtube_cookies_can_be_uploaded_and_are_encrypted_at_rest(): void
    {
        $cookies = "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t2147483647\tSID\tsecret-session-value\n";

        $this->put('/admin/settings/youtube', [
            'youtube_cookies_file' => UploadedFile::fake()->createWithContent('cookies.txt', $cookies),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($cookies, StorageSetting::firstOrFail()->youtube_cookies);
        $raw = DB::table('storage_settings')->value('youtube_cookies');
        $this->assertStringNotContainsString('secret-session-value', $raw);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('YouTube cookies are configured')
            ->assertDontSee('secret-session-value');
    }

    public function test_invalid_youtube_cookie_file_is_rejected(): void
    {
        $this->put('/admin/settings/youtube', [
            'youtube_cookies_file' => UploadedFile::fake()->createWithContent('cookies.txt', 'cookies.txt'),
        ])->assertSessionHasErrors('youtube_cookies_file');

        $this->assertNull(StorageSetting::firstOrFail()->youtube_cookies);
    }

    public function test_saved_youtube_cookies_can_be_removed(): void
    {
        StorageSetting::firstOrFail()->update(['youtube_cookies' => "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t1\tSID\tx\n"]);

        $this->put('/admin/settings/youtube', ['remove_youtube_cookies' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(StorageSetting::firstOrFail()->youtube_cookies);
    }

    public function test_edit_page_offers_local_and_s3_as_radio_buttons_with_local_selected_by_default(): void
    {
        $html = $this->get('/admin/settings')->assertOk()
            ->assertSee('Storage type')
            ->assertSee('Local disk')
            ->assertSee('S3-compatible storage')
            ->getContent();

        $this->assertMatchesRegularExpression('/type="radio" name="driver" value="local"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/type="radio" name="driver" value="s3"[^>]*\schecked/', $html);
        // The S3 credential fields are tucked away until S3 is chosen.
        $this->assertMatchesRegularExpression('/data-storage-s3\s+hidden/', $html);
    }

    public function test_edit_page_opens_the_s3_fields_when_s3_is_the_saved_type(): void
    {
        $this->fakeS3();
        $this->put('/admin/settings/storage', self::S3_FORM)->assertRedirect();

        $html = $this->get('/admin/settings')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/type="radio" name="driver" value="s3"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-storage-s3\s+hidden/', $html);
    }

    public function test_update_saves_and_applies_to_config(): void
    {
        $this->put('/admin/settings/storage', [
            'driver' => 'local',
            'image_directory' => 'uploads/images',
            'audio_directory' => 'uploads/audio',
        ])->assertRedirect()->assertSessionHas('settings_tab', 'storage');

        $setting = StorageSetting::firstOrFail();
        $this->assertSame('uploads/images', $setting->image_directory);
        $this->assertSame('uploads/audio', $setting->audio_directory);

        $this->assertSame('uploads/images', config('services.media_storage.image_directory'));
        $this->assertSame('uploads/audio', config('services.media_storage.audio_directory'));
        $this->assertSame('public', config('services.media_storage.disk'));
    }

    public function test_the_storage_type_is_required_and_must_be_one_of_the_offered_ones(): void
    {
        $folders = ['image_directory' => 'media-contents/thumbnails', 'audio_directory' => 'media-contents/streams'];

        $this->put('/admin/settings/storage', $folders)->assertSessionHasErrors('driver');
        $this->put('/admin/settings/storage', [...$folders, 'driver' => 'ftp'])->assertSessionHasErrors('driver');
    }

    public function test_rejects_a_directory_that_tries_to_escape_the_disk_root(): void
    {
        $this->put('/admin/settings/storage', [
            'driver' => 'local',
            'image_directory' => '../../etc',
            'audio_directory' => 'media-contents/streams',
        ])->assertSessionHasErrors('image_directory');

        $this->assertSame('media-contents/thumbnails', StorageSetting::firstOrFail()->image_directory);
    }

    public function test_choosing_s3_saves_the_credentials_encrypted_and_points_uploads_at_it(): void
    {
        $this->fakeS3();

        $this->put('/admin/settings/storage', self::S3_FORM)
            ->assertRedirect()
            ->assertSessionHas('status', 'Storage settings saved — connected to the bucket.');

        $setting = StorageSetting::firstOrFail();
        $this->assertSame('s3', $setting->driver);
        $this->assertSame('super-secret-value', $setting->s3_secret);
        $this->assertSame('AKIATESTKEY123', $setting->s3_key);
        $this->assertSame('radio-media', $setting->s3_bucket);

        // Encrypted at rest: neither credential appears in the table as plain text.
        $row = DB::table('storage_settings')->first();
        $this->assertStringNotContainsString('super-secret-value', $row->s3_secret);
        $this->assertStringNotContainsString('AKIATESTKEY123', $row->s3_key);

        $this->assertSame('media_s3', config('services.media_storage.disk'));
        $this->assertSame('radio-media', config('filesystems.disks.media_s3.bucket'));
        // The connection test cleaned up after itself.
        $this->assertSame([], Storage::disk('media_s3')->allFiles());
    }

    public function test_the_secret_is_never_sent_back_to_the_browser(): void
    {
        $this->fakeS3();
        $this->put('/admin/settings/storage', self::S3_FORM);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('super-secret-value')
            ->assertSee('AKIATESTKEY123')
            ->assertSee('Saved — leave blank to keep it');
    }

    public function test_choosing_s3_without_credentials_is_rejected(): void
    {
        $this->put('/admin/settings/storage', [
            'driver' => 's3',
            'image_directory' => 'media-contents/thumbnails',
            'audio_directory' => 'media-contents/streams',
        ])->assertSessionHasErrors(['s3_key', 's3_secret', 's3_bucket', 's3_region']);

        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_leaving_the_key_and_secret_blank_keeps_the_saved_ones(): void
    {
        $this->fakeS3();
        $this->put('/admin/settings/storage', self::S3_FORM)->assertRedirect();

        $this->put('/admin/settings/storage', [...self::S3_FORM, 's3_key' => '', 's3_secret' => '', 'image_directory' => 'covers'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = StorageSetting::firstOrFail();
        $this->assertSame('covers', $setting->image_directory);
        $this->assertSame('AKIATESTKEY123', $setting->s3_key);
        $this->assertSame('super-secret-value', $setting->s3_secret);
    }

    public function test_storage_that_cannot_be_written_to_is_refused_and_nothing_is_saved(): void
    {
        $this->fakeS3();
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException('Access Denied'));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_s3', $disk);

        $this->put('/admin/settings/storage', self::S3_FORM)->assertRedirect()->assertSessionHasErrors('storage_connection');

        $this->assertStringContainsString('Access Denied', session('errors')->first('storage_connection'));
        $setting = StorageSetting::firstOrFail();
        $this->assertSame('local', $setting->driver);
        $this->assertNull($setting->s3_secret);
        // Nothing the admin typed is flashed back — least of all the secret.
        $this->assertNull(session()->getOldInput('s3_secret'));
        $this->assertSame('AKIATESTKEY123', session()->getOldInput('s3_key'));
    }

    public function test_switching_back_to_local_keeps_the_s3_credentials_for_files_already_there(): void
    {
        $this->fakeS3();
        $this->put('/admin/settings/storage', self::S3_FORM)->assertRedirect();

        $this->put('/admin/settings/storage', [...Arr::except(self::S3_FORM, ['s3_key', 's3_secret']), 'driver' => 'local'])->assertRedirect();

        $setting = StorageSetting::firstOrFail();
        $this->assertSame('local', $setting->driver);
        $this->assertSame('super-secret-value', $setting->s3_secret);
        $this->assertSame('public', config('services.media_storage.disk'));
        // Files uploaded while S3 was selected still resolve against it.
        $this->assertSame('radio-media', config('filesystems.disks.media_s3.bucket'));
    }

    public function test_unreadable_saved_credentials_do_not_break_the_app_and_uploads_fall_back_to_local(): void
    {
        DB::table('storage_settings')->update([
            'driver' => 's3', 's3_key' => 'not-encrypted', 's3_secret' => 'not-encrypted', 's3_bucket' => 'radio-media', 's3_region' => 'auto',
        ]);

        StorageSetting::firstOrFail()->applyToConfig();
        $this->assertSame('public', config('services.media_storage.disk'));

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('can no longer be read');
    }
}
