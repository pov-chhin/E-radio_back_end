<?php

namespace Tests\Feature;

use App\Support\MediaStorage;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Src\Setting\Models\RustfsSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\UsesRemoteStorage;
use Tests\TestCase;

/**
 * Uploads can succeed while every download is refused (HTTP 403) — a bucket nobody but the app may read. The
 * browser and the mobile app load images and audio straight from the bucket, so Settings → Storage checks for it
 * and can fix it.
 */
class PublicReadAccessTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase, UsesRemoteStorage;

    private const RUSTFS_FORM = [
        'rustfs_key' => 'RUSTFSKEY123',
        'rustfs_secret' => 'rustfs-secret-value',
        'rustfs_bucket' => 'rustfs',
        'rustfs_region' => 'us-east-1',
        'rustfs_endpoint' => 'http://rustfs.example.com:9000',
        'rustfs_url' => '',
        'rustfs_use_path_style_endpoint' => '1',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    public function test_files_a_stranger_can_fetch_are_publicly_readable(): void
    {
        $this->useRemoteStorage('rustfs', MediaStorage::RUSTFS);
        Http::fake(['cdn.example.com/*' => Http::response('ok', 200)]);

        $this->assertNull(MediaStorage::publicReadError(MediaStorage::RUSTFS));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://cdn.example.com/media-contents/thumbnails/.eradio-public-read-test-'));
        $this->assertSame([], Storage::disk(MediaStorage::RUSTFS)->allFiles());
    }

    public function test_a_bucket_that_refuses_anonymous_downloads_is_reported_with_the_http_status(): void
    {
        $this->useRemoteStorage('rustfs', MediaStorage::RUSTFS);
        Http::fake(['cdn.example.com/*' => Http::response('', 403)]);

        $problem = MediaStorage::publicReadError(MediaStorage::RUSTFS);

        $this->assertStringContainsString('HTTP 403', $problem);
        $this->assertStringContainsString('broken links', $problem);
        $this->assertSame([], Storage::disk(MediaStorage::RUSTFS)->allFiles());
    }

    public function test_a_web_page_answering_in_place_of_the_file_is_reported(): void
    {
        $this->useRemoteStorage('rustfs', MediaStorage::RUSTFS);
        // What a storage console or a proxy's catch-all answers for any path: 200, but a page instead of the file.
        Http::fake(['cdn.example.com/*' => Http::response('<!doctype html><html></html>', 200, ['Content-Type' => 'text/html'])]);

        $problem = MediaStorage::publicReadError(MediaStorage::RUSTFS);

        $this->assertStringContainsString('not with the stored file', $problem);
        $this->assertStringContainsString('text/html', $problem);
        $this->assertStringContainsString('web console', $problem);
        $this->assertSame([], Storage::disk(MediaStorage::RUSTFS)->allFiles());
    }

    public function test_an_unreachable_public_address_is_reported(): void
    {
        $this->useRemoteStorage('rustfs', MediaStorage::RUSTFS);
        Http::fake(['cdn.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->assertStringContainsString('timed out', MediaStorage::publicReadError(MediaStorage::RUSTFS));
    }

    /**
     * Defines the disk for the address and keys in RUSTFS_FORM — exactly, because saving rebuilds the disk whenever
     * they differ — and swaps in an in-memory double, so "connecting" never leaves the test.
     */
    private function fakeRustfsForTheForm(): void
    {
        RustfsSetting::firstOrFail()->fill([
            's3_key' => self::RUSTFS_FORM['rustfs_key'],
            's3_secret' => self::RUSTFS_FORM['rustfs_secret'],
            's3_endpoint' => self::RUSTFS_FORM['rustfs_endpoint'],
            's3_bucket' => self::RUSTFS_FORM['rustfs_bucket'],
            's3_region' => self::RUSTFS_FORM['rustfs_region'],
        ])->applyToConfig();
        Storage::fake(MediaStorage::RUSTFS, ['url' => 'https://cdn.example.com']);
    }

    public function test_saving_a_connection_to_a_private_bucket_warns_that_files_cannot_be_read(): void
    {
        $this->fakeRustfsForTheForm();
        Http::fake(['cdn.example.com/*' => Http::response('', 403)]);

        $this->put('/admin/settings/rustfs', self::RUSTFS_FORM)
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('public_read_warning.disk', MediaStorage::RUSTFS);

        $this->get('/admin/settings')->assertSee('HTTP 403')->assertSee('Make files public');
    }

    public function test_saving_a_connection_to_a_readable_bucket_shows_no_warning(): void
    {
        $this->fakeRustfsForTheForm();
        Http::fake(['cdn.example.com/*' => Http::response('ok', 200)]);

        $this->put('/admin/settings/rustfs', self::RUSTFS_FORM)
            ->assertRedirect()
            ->assertSessionMissing('public_read_warning');
    }

    /** A storage whose bucket client is a double, so the policy the app sends can be inspected. */
    private function fakeBucket(object $client): void
    {
        $this->useRemoteStorage('rustfs', MediaStorage::RUSTFS);

        $disk = Mockery::mock();
        $disk->shouldReceive('getClient')->andReturn($client);
        $disk->shouldReceive('put')->andReturn(true);
        $disk->shouldReceive('url')->andReturnUsing(fn (string $path) => 'https://cdn.example.com/'.$path);
        $disk->shouldReceive('delete');
        Storage::set(MediaStorage::RUSTFS, $disk);
    }

    private function awsError(string $operation, string $code, int $status = 404): AwsException
    {
        return new AwsException("{$code} (client): {$operation} failed", new Command($operation), ['code' => $code, 'response' => new \GuzzleHttp\Psr7\Response($status)]);
    }

    public function test_make_files_public_lets_anyone_read_only_the_image_and_audio_folders(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('getBucketPolicy')->andThrow($this->awsError('GetBucketPolicy', 'NoSuchBucketPolicy'));
        $sent = null;
        $client->shouldReceive('putBucketPolicy')->once()->andReturnUsing(function (array $args) use (&$sent) {
            $sent = $args;
        });
        $this->fakeBucket($client);
        Http::fake(['cdn.example.com/*' => Http::response('ok', 200)]);

        $this->post('/admin/settings/storage/public-read', ['disk' => MediaStorage::RUSTFS])
            ->assertRedirect()
            ->assertSessionHas('settings_tab', 'rustfs')
            ->assertSessionHas('status')
            ->assertSessionMissing('public_read_warning');

        $this->assertSame('rustfs', $sent['Bucket']);
        $statement = json_decode($sent['Policy'], true)['Statement'];
        $this->assertCount(1, $statement);
        $this->assertSame('Allow', $statement[0]['Effect']);
        $this->assertSame(['s3:GetObject'], $statement[0]['Action']);
        $this->assertSame(['AWS' => ['*']], $statement[0]['Principal']);
        $this->assertSame(
            ['arn:aws:s3:::rustfs/media-contents/thumbnails/*', 'arn:aws:s3:::rustfs/media-contents/streams/*'],
            $statement[0]['Resource'],
        );
    }

    public function test_make_files_public_keeps_the_statements_already_in_the_policy_and_never_duplicates_its_own(): void
    {
        $existing = [
            'Version' => '2012-10-17',
            'Statement' => [
                ['Sid' => 'Other', 'Effect' => 'Allow', 'Principal' => ['AWS' => ['arn:aws:iam::1:user/x']], 'Action' => ['s3:ListBucket'], 'Resource' => ['arn:aws:s3:::rustfs']],
                ['Sid' => 'EradioPublicRead', 'Effect' => 'Allow', 'Principal' => ['AWS' => ['*']], 'Action' => ['s3:GetObject'], 'Resource' => ['arn:aws:s3:::rustfs/old/*']],
            ],
        ];
        $client = Mockery::mock();
        $client->shouldReceive('getBucketPolicy')->andReturn(['Policy' => json_encode($existing)]);
        $sent = null;
        $client->shouldReceive('putBucketPolicy')->once()->andReturnUsing(function (array $args) use (&$sent) {
            $sent = $args;
        });
        $this->fakeBucket($client);
        Http::fake(['cdn.example.com/*' => Http::response('ok', 200)]);

        $this->post('/admin/settings/storage/public-read', ['disk' => MediaStorage::RUSTFS])->assertRedirect();

        $statements = json_decode($sent['Policy'], true)['Statement'];
        $this->assertSame(['Other', 'EradioPublicRead'], array_column($statements, 'Sid'));
        $this->assertStringContainsString('media-contents/thumbnails', json_encode($statements[1]['Resource'], JSON_UNESCAPED_SLASHES));
        $this->assertStringNotContainsString('old/*', json_encode($statements, JSON_UNESCAPED_SLASHES));
    }

    public function test_make_files_public_reports_a_key_that_may_not_change_policies(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('getBucketPolicy')->andThrow($this->awsError('GetBucketPolicy', 'AccessDenied', 403));
        $client->shouldNotReceive('putBucketPolicy');
        $this->fakeBucket($client);

        $this->post('/admin/settings/storage/public-read', ['disk' => MediaStorage::RUSTFS])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('AccessDenied', session('error'));
    }

    public function test_make_files_public_still_warns_when_the_files_stay_unreadable(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('getBucketPolicy')->andThrow($this->awsError('GetBucketPolicy', 'NoSuchBucketPolicy'));
        $client->shouldReceive('putBucketPolicy')->once();
        $this->fakeBucket($client);
        Http::fake(['cdn.example.com/*' => Http::response('', 403)]);

        $this->post('/admin/settings/storage/public-read', ['disk' => MediaStorage::RUSTFS])
            ->assertRedirect()
            ->assertSessionHas('public_read_warning.disk', MediaStorage::RUSTFS);
    }

    public function test_make_files_public_only_accepts_a_remote_storage_disk(): void
    {
        $this->post('/admin/settings/storage/public-read', ['disk' => 'public'])->assertSessionHasErrors('disk');
        $this->post('/admin/settings/storage/public-read', [])->assertSessionHasErrors('disk');
    }

    public function test_make_files_public_needs_the_manage_settings_permission(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin')
            ->post('/admin/settings/storage/public-read', ['disk' => MediaStorage::RUSTFS])
            ->assertForbidden();
    }
}
