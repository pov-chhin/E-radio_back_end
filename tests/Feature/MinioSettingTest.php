<?php

namespace Tests\Feature;

use Aws\Command;
use Aws\Exception\AwsException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Src\Setting\Models\MinioSetting;
use Src\Setting\Models\RustfsSetting;
use Src\Setting\Models\StorageSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class MinioSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    /** What the tab's form posts once the admin has added the address and keys to the pre-filled bucket. */
    private const FORM = [
        'minio_key' => 'MINIOKEY123',
        'minio_secret' => 'minio-secret-value',
        'minio_bucket' => 'e-radio-media',
        'minio_region' => 'us-east-1',
        'minio_endpoint' => 'http://minio.devams.site:9000',
        'minio_url' => '',
        'minio_use_path_style_endpoint' => '1',
    ];

    private const FOLDERS = ['image_directory' => 'media-contents/thumbnails', 'audio_directory' => 'media-contents/streams'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    /** Defines the MinIO disk for the address and keys in FORM and swaps in an in-memory double, so "connecting" never leaves the test. */
    private function fakeMinio(): void
    {
        MinioSetting::firstOrFail()->fill([
            's3_key' => self::FORM['minio_key'],
            's3_secret' => self::FORM['minio_secret'],
            's3_endpoint' => self::FORM['minio_endpoint'],
        ])->applyToConfig();

        Storage::fake('media_minio');
    }

    private function connectMinio(): void
    {
        $this->fakeMinio();
        $this->put('/admin/settings/minio', self::FORM)->assertRedirect()->assertSessionHasNoErrors();
    }

    /** A write that the server refuses, whatever the reason. */
    private function refuseWrites(string $reason): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException($reason));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_minio', $disk);
    }

    public function test_the_settings_page_has_a_minio_tab_with_the_bucket_prefilled_and_no_address(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('data-settings-tab="minio"', false)
            ->assertSee('MinIO Settings')
            ->assertSee('value="e-radio-media"', false)
            ->assertSee('placeholder="http://minio.example.com:9000"', false)
            ->assertSee('Not set up yet');
    }

    public function test_the_minio_storage_type_cannot_be_picked_until_the_connection_is_set_up(): void
    {
        $html = $this->get('/admin/settings')->assertOk()->assertSee('Set it up in the MinIO tab first.')->getContent();
        $this->assertMatchesRegularExpression('/value="minio"[^>]*\sdisabled/', $html);

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'minio'])
            ->assertSessionHasErrors(['driver' => 'Set up the MinIO connection in the MinIO tab first.']);
        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_saving_the_connection_stores_the_keys_encrypted_and_defines_the_disk(): void
    {
        $this->fakeMinio();

        $this->put('/admin/settings/minio', self::FORM)
            ->assertRedirect()
            ->assertSessionHas('status', 'MinIO connection saved — connected to the bucket.')
            ->assertSessionHas('settings_tab', 'minio');

        $setting = MinioSetting::firstOrFail();
        $this->assertSame('MINIOKEY123', $setting->s3_key);
        $this->assertSame('minio-secret-value', $setting->s3_secret);
        $this->assertSame('http://minio.devams.site:9000', $setting->s3_endpoint);
        $this->assertTrue($setting->s3_use_path_style_endpoint);

        $row = DB::table('minio_settings')->first();
        $this->assertStringNotContainsString('minio-secret-value', $row->s3_secret);
        $this->assertStringNotContainsString('MINIOKEY123', $row->s3_key);

        $this->assertSame('e-radio-media', config('filesystems.disks.media_minio.bucket'));
        $this->assertSame('http://minio.devams.site:9000', config('filesystems.disks.media_minio.endpoint'));
        $this->assertTrue(config('filesystems.disks.media_minio.use_path_style_endpoint'));
        // The connection test cleaned up after itself, and saving alone does not change where uploads go.
        $this->assertSame([], Storage::disk('media_minio')->allFiles());
        $this->assertSame('public', config('services.media_storage.disk'));
    }

    public function test_the_secret_is_never_sent_back_and_the_tab_shows_the_connection_is_saved(): void
    {
        $this->connectMinio();

        $this->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('minio-secret-value')
            ->assertSee('MINIOKEY123')
            ->assertSee('Saved — leave blank to keep it')
            ->assertSee('Connection saved')
            ->assertDontSee('Set it up in the MinIO tab first.');
    }

    public function test_the_address_and_keys_are_required_the_first_time(): void
    {
        $this->put('/admin/settings/minio', ['minio_bucket' => '', 'minio_region' => '', 'minio_endpoint' => ''])
            ->assertSessionHasErrors(['minio_key', 'minio_secret', 'minio_bucket', 'minio_region', 'minio_endpoint']);

        $this->assertNull(MinioSetting::firstOrFail()->s3_secret);
    }

    public function test_the_keys_may_come_from_env_but_the_connection_still_needs_an_address(): void
    {
        config(['services.minio.key' => 'ENVKEY123', 'services.minio.secret' => 'env-secret-value']);

        // Keys alone are not a connection: with no address the SDK would talk to Amazon S3, not to MinIO.
        $this->assertNull(MinioSetting::firstOrFail()->s3Disk());

        MinioSetting::firstOrFail()->fill(['s3_endpoint' => self::FORM['minio_endpoint']])->applyToConfig();
        Storage::fake('media_minio');

        $this->assertSame('ENVKEY123', config('filesystems.disks.media_minio.key'));
        $this->assertSame('env-secret-value', config('filesystems.disks.media_minio.secret'));

        $this->put('/admin/settings/minio', [...self::FORM, 'minio_key' => '', 'minio_secret' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = DB::table('minio_settings')->first();
        $this->assertNull($row->s3_key);
        $this->assertNull($row->s3_secret);
        $this->assertSame('http://minio.devams.site:9000', $row->s3_endpoint);
    }

    public function test_a_connection_that_cannot_be_written_to_is_refused_and_nothing_is_saved(): void
    {
        $this->fakeMinio();
        $this->refuseWrites('SignatureDoesNotMatch');

        $this->put('/admin/settings/minio', self::FORM)->assertRedirect()->assertSessionHasErrors('minio_connection');

        $this->assertStringContainsString('SignatureDoesNotMatch', session('errors')->first('minio_connection'));
        $this->assertNull(MinioSetting::firstOrFail()->s3_secret);
        $this->assertNull(session()->getOldInput('minio_secret'));
        $this->assertSame('MINIOKEY123', session()->getOldInput('minio_key'));

        // The page reopens on the MinIO tab to show the error, and leaves the other tabs' forms alone.
        $html = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="active"\s+data-settings-tab="minio"/', $html);
    }

    public function test_an_address_that_is_not_an_s3_api_gets_a_message_that_says_so(): void
    {
        $this->fakeMinio();

        // What the SDK throws when the "endpoint" is really a web console: the reply is a web page, not XML.
        $notXml = new AwsException(
            'Error executing "PutObject" on "http://minio.devams.site/e-radio-media/x"; AWS HTTP error: Unable to parse error information from response - Error parsing XML: String could not be parsed as XML',
            new Command('PutObject'),
            ['response' => new Response(405)],
        );
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException('Unable to write file', 0, $notXml));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_minio', $disk);

        $this->put('/admin/settings/minio', self::FORM)->assertSessionHasErrors('minio_connection');

        $message = session('errors')->first('minio_connection');
        $this->assertStringContainsString('not like an S3 API (HTTP 405', $message);
        $this->assertStringContainsString('not the web console', $message);
        $this->assertStringNotContainsString('Error parsing XML', $message);
        $this->assertNull(MinioSetting::firstOrFail()->s3_secret);
    }

    public function test_files_are_read_from_the_env_public_url_when_none_is_saved(): void
    {
        config(['services.minio.url' => 'https://cdn.example.com/e-radio-media']);
        $minio = MinioSetting::firstOrFail()->fill([
            's3_key' => 'k', 's3_secret' => 's', 's3_endpoint' => 'https://s3.example.com/', 's3_url' => null,
        ]);
        $minio->applyToConfig();

        $this->assertSame('https://cdn.example.com/e-radio-media', config('filesystems.disks.media_minio.url'));
        $this->assertSame(
            'https://cdn.example.com/e-radio-media/media-contents/thumbnails/a.webp',
            Storage::disk('media_minio')->url('media-contents/thumbnails/a.webp'),
        );

        $this->get('/admin/settings')->assertOk()->assertSee('From .env: https://cdn.example.com/e-radio-media — leave blank to use it');
    }

    public function test_a_public_url_saved_in_the_form_wins_over_the_one_in_env(): void
    {
        config(['services.minio.url' => 'https://cdn.example.com/e-radio-media']);
        MinioSetting::firstOrFail()->fill([
            's3_key' => 'k', 's3_secret' => 's', 's3_endpoint' => 'https://s3.example.com/', 's3_url' => 'https://files.example.com/media',
        ])->applyToConfig();

        $this->assertSame('https://files.example.com/media', config('filesystems.disks.media_minio.url'));
    }

    public function test_without_any_public_url_files_are_read_from_the_endpoint_and_bucket(): void
    {
        config(['services.minio.url' => null]);
        MinioSetting::firstOrFail()->fill([
            's3_key' => 'k', 's3_secret' => 's', 's3_endpoint' => 'https://s3.example.com/', 's3_url' => null,
        ])->applyToConfig();

        $this->assertNull(config('filesystems.disks.media_minio.url'));
        $this->assertSame(
            'https://s3.example.com/e-radio-media/media-contents/thumbnails/a.webp',
            Storage::disk('media_minio')->url('media-contents/thumbnails/a.webp'),
        );
    }

    public function test_an_address_that_reaches_the_web_console_gets_a_message_that_says_so(): void
    {
        $this->fakeMinio();

        // What MinIO answers when an S3 request lands on the port of its web console.
        $onConsolePort = new AwsException(
            'Error executing "PutObject" on "https://s3.devams.site/e-radio-media/x"; AWS HTTP error: Client error: `PUT https://s3.devams.site/e-radio-media/x` resulted in a `400 Bad Request` response: InvalidArgument S3 API Requests must be made to API port',
            new Command('PutObject'),
            ['response' => new Response(400), 'code' => 'InvalidArgument', 'message' => 'S3 API Requests must be made to API port'],
        );
        $driver = Mockery::mock();
        $driver->shouldReceive('write')->andThrow(new RuntimeException('Unable to write file', 0, $onConsolePort));
        $disk = Mockery::mock();
        $disk->shouldReceive('getDriver')->andReturn($driver);
        Storage::set('media_minio', $disk);

        $this->put('/admin/settings/minio', self::FORM)->assertSessionHasErrors('minio_connection');

        $message = session('errors')->first('minio_connection');
        $this->assertStringContainsString("MinIO's web console, not its S3 API", $message);
        $this->assertStringContainsString('usually port 9000', $message);
        $this->assertNull(MinioSetting::firstOrFail()->s3_secret);
    }

    public function test_once_set_up_minio_can_be_chosen_as_the_storage_type_and_uploads_follow_it(): void
    {
        $this->connectMinio();

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'minio'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('settings_tab', 'storage');

        $this->assertSame('minio', StorageSetting::firstOrFail()->driver);
        $this->assertSame('media_minio', config('services.media_storage.disk'));
        $this->assertSame([], Storage::disk('media_minio')->allFiles());

        $html = $this->get('/admin/settings')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="minio"[^>]*\schecked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="minio"[^>]*\sdisabled/', $html);
    }

    public function test_choosing_minio_when_it_cannot_be_written_to_is_refused(): void
    {
        $this->connectMinio();
        $this->refuseWrites('Access Denied');

        $this->put('/admin/settings/storage', [...self::FOLDERS, 'driver' => 'minio'])->assertSessionHasErrors('storage_connection');

        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_minio_and_rustfs_are_separate_connections(): void
    {
        $this->connectMinio();

        // Saving MinIO touched neither the RustFS row nor the RustFS disk.
        $this->assertNull(RustfsSetting::firstOrFail()->s3_key);
        $this->assertNull(config('filesystems.disks.media_rustfs'));

        // A stale MinIO row cannot leave it selected when its keys are unreadable: uploads fall back to local.
        DB::table('minio_settings')->update(['s3_key' => 'not-encrypted', 's3_secret' => 'not-encrypted']);
        DB::table('storage_settings')->update(['driver' => 'minio']);

        StorageSetting::firstOrFail()->applyToConfig();

        $this->assertSame('public', config('services.media_storage.disk'));
        $this->get('/admin/settings')->assertOk()->assertSee('The saved MinIO credentials can no longer be read');
    }
}
