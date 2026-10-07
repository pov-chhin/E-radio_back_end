<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Jobs\ProcessMediaContentAudio;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Services\OpusAudioProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Setting\Models\StorageSetting;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\UsesRemoteStorage;
use Tests\TestCase;

class MediaContentUploadsTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase, UsesRemoteStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Queue::fake();
        $this->actingAs($this->createAdmin(['view-media-content', 'create-media-content', 'edit-media-content', 'delete-media-content']), 'admin');
    }

    private function category(): Category
    {
        return Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
    }

    private function fakeImageBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function fakeWavUpload(string $name = 'track.wav'): UploadedFile
    {
        $sampleRate = 8000;
        $audio = str_repeat(chr(128), 800);
        $header = 'RIFF'.pack('V', 36 + strlen($audio)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate, 1, 8)
            .'data'.pack('V', strlen($audio));

        return UploadedFile::fake()->createWithContent($name, $header.$audio);
    }

    private function processPendingAudio(MediaContent $mediaContent): void
    {
        $mediaContent->refresh();
        (new ProcessMediaContentAudio($mediaContent->id, $mediaContent->source_path))
            ->handle(app(OpusAudioProcessor::class));
        $mediaContent->refresh();
    }

    public function test_uploading_a_thumbnail_keeps_its_aspect_ratio_and_is_converted_to_webp(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 2000, 500),
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertStringEndsWith('.webp', $mediaContent->thumbnail);

        // 2000×500 is scaled down to fit 1280 wide, not cropped to 16:9.
        $image = imagecreatefromstring(Storage::disk('public')->get($mediaContent->thumbnail));
        $this->assertSame([1280, 320], [imagesx($image), imagesy($image)]);
        $this->assertStringContainsString('/storage/', $mediaContent->thumbnail_url);
    }

    public function test_thumbnail_upload_respects_the_admin_configured_image_folder(): void
    {
        $setting = StorageSetting::firstOrFail();
        $setting->update(['image_directory' => 'custom/images']);
        $setting->applyToConfig();

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 2000, 500),
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertStringStartsWith('custom/images/', $mediaContent->thumbnail);
    }

    public function test_a_pasted_thumbnail_url_is_downloaded_and_processed(): void
    {
        Http::fake(['cdn.example.com/*' => Http::response($this->fakeImageBytes(1000, 1000), 200)]);

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail_url' => 'https://cdn.example.com/thumb.png',
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        // Already smaller than the 1280 cap, so it is neither cropped nor scaled up.
        $image = imagecreatefromstring(Storage::disk('public')->get($mediaContent->thumbnail));
        $this->assertSame([1000, 1000], [imagesx($image), imagesy($image)]);
    }

    public function test_uploading_audio_returns_immediately_and_the_queue_converts_it_to_opus(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload(),
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame('processing', $mediaContent->processing_status);
        $this->assertNull($mediaContent->stream);
        Storage::disk('public')->assertExists($mediaContent->source_path);
        Queue::assertPushed(ProcessMediaContentAudio::class, fn ($job) => $job->mediaContentId === $mediaContent->id);

        $this->processPendingAudio($mediaContent);

        $this->assertSame('ready', $mediaContent->processing_status);
        $this->assertNull($mediaContent->source_path);
        $this->assertStringEndsWith('.opus', $mediaContent->stream);
        Storage::disk('public')->assertExists($mediaContent->stream);
        $this->assertStringStartsWith('OggS', Storage::disk('public')->get($mediaContent->stream));
        $this->assertSame(Storage::disk('public')->size($mediaContent->stream), $mediaContent->stream_size);
        $this->assertMatchesRegularExpression('/\d+(?:\.\d)? (?:B|KB|MB)/', $mediaContent->stream_size_label);
        $this->assertStringContainsString('/storage/', $mediaContent->stream_url);
    }

    public function test_audio_conversion_respects_the_admin_configured_audio_folder(): void
    {
        $setting = StorageSetting::firstOrFail();
        $setting->update(['audio_directory' => 'custom/audio']);
        $setting->applyToConfig();

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload(),
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->processPendingAudio($mediaContent);

        $this->assertStringStartsWith('custom/audio/', $mediaContent->stream);
    }

    public function test_the_worker_extracts_video_audio_and_converts_it_to_opus(): void
    {
        $temporaryBase = tempnam(sys_get_temp_dir(), 'video_fixture_');
        $videoPath = $temporaryBase.'.mp4';
        @unlink($temporaryBase);

        try {
            (new Process([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error', '-y',
                '-f', 'lavfi', '-i', 'color=c=black:s=16x16:d=0.2',
                '-f', 'lavfi', '-i', 'sine=frequency=440:duration=0.2',
                '-shortest', '-c:v', 'libx264', '-c:a', 'aac',
                $videoPath,
            ]))->mustRun();

            $this->post('/admin/media-contents', [
                'category_id' => $this->category()->id,
                'title' => 'Video Track', 'slug' => 'video-track', 'media_type' => 'audio', 'status' => 'private',
                'stream' => new UploadedFile($videoPath, 'clip.mp4', 'video/mp4', null, true),
            ])->assertRedirect(route('admin.media-contents.index'));

            $mediaContent = MediaContent::where('slug', 'video-track')->firstOrFail();
            $this->assertSame('processing', $mediaContent->processing_status);

            $this->processPendingAudio($mediaContent);

            $this->assertSame('ready', $mediaContent->processing_status);
            $this->assertStringEndsWith('.opus', $mediaContent->stream);
            $this->assertStringStartsWith('OggS', Storage::disk('public')->get($mediaContent->stream));
        } finally {
            @unlink($videoPath);
        }
    }

    public function test_the_worker_records_the_audio_duration_probed_from_the_uploaded_file(): void
    {
        $temporaryBase = tempnam(sys_get_temp_dir(), 'audio_fixture_');
        $audioPath = $temporaryBase.'.wav';
        @unlink($temporaryBase);

        try {
            (new Process([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error', '-y',
                '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
                $audioPath,
            ]))->mustRun();

            $this->post('/admin/media-contents', [
                'category_id' => $this->category()->id,
                'title' => 'Timed Track', 'slug' => 'timed-track', 'media_type' => 'audio', 'status' => 'private',
                'duration_seconds' => 999, // whatever the admin typed in — the worker should overwrite it
                'stream' => new UploadedFile($audioPath, 'timed.wav', 'audio/wav', null, true),
            ])->assertRedirect(route('admin.media-contents.index'));

            $mediaContent = MediaContent::where('slug', 'timed-track')->firstOrFail();
            $this->assertSame(999, $mediaContent->duration_seconds);

            $this->processPendingAudio($mediaContent);

            $this->assertSame('ready', $mediaContent->processing_status);
            $this->assertSame(2, $mediaContent->duration_seconds);
        } finally {
            @unlink($audioPath);
        }
    }

    public function test_a_pasted_stream_url_is_stored_directly_without_being_downloaded(): void
    {
        Http::fake(); // any request here would mean we wrongly tried to download it

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream_url' => 'https://stream.example.com/live.mp3',
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame('https://stream.example.com/live.mp3', $mediaContent->stream);
        $this->assertSame('https://stream.example.com/live.mp3', $mediaContent->stream_url);
        $this->assertSame('External', $mediaContent->stream_size_label);
        Http::assertNothingSent();
    }

    public function test_youtube_and_facebook_links_are_labelled_by_provider(): void
    {
        $labels = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'YouTube',
            'https://youtu.be/dQw4w9WgXcQ' => 'YouTube',
            'https://music.youtube.com/watch?v=dQw4w9WgXcQ' => 'YouTube',
            'https://www.facebook.com/somepage/videos/1234567890/' => 'Facebook',
            'https://fb.watch/AbCdEf/' => 'Facebook',
            'https://notyoutube.com/watch?v=dQw4w9WgXcQ' => 'External',
            'https://cdn.example.com/pilot.mp3' => 'External',
        ];

        foreach ($labels as $url => $label) {
            // Not saved: the label only depends on the address.
            $this->assertSame($label, (new MediaContent(['stream' => $url]))->stream_size_label, $url);
        }
    }

    private function mediaContentWithStream(?string $stream, array $overrides = []): MediaContent
    {
        return MediaContent::create(array_merge([
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $stream, 'processing_status' => 'ready', 'duration_seconds' => 1165,
        ], $overrides));
    }

    public function test_the_edit_form_shows_the_uploaded_file_with_its_size_length_and_a_play_button(): void
    {
        Storage::disk('public')->put('audio/abc123.opus', str_repeat('a', 2048));
        $mediaContent = $this->mediaContentWithStream('audio/abc123.opus');

        $this->get(route('admin.media-contents.edit', $mediaContent))
            ->assertOk()
            ->assertSee('abc123.opus')
            ->assertSee('Uploaded file')
            ->assertSee('2.0 KB')
            ->assertSee('19:25')
            ->assertSee('data-stream-url="'.$mediaContent->stream_url.'"', false)
            ->assertDontSee('File missing from storage');
    }

    public function test_the_edit_form_says_so_when_the_uploaded_file_is_gone_from_storage(): void
    {
        $mediaContent = $this->mediaContentWithStream('audio/gone.opus');

        $this->get(route('admin.media-contents.edit', $mediaContent))
            ->assertOk()
            ->assertSee('gone.opus')
            ->assertSee('File missing from storage')
            ->assertDontSee('data-preview-toggle', false);
    }

    public function test_the_edit_form_shows_an_external_link_without_a_size(): void
    {
        $mediaContent = $this->mediaContentWithStream('https://stream.example.com/live.mp3');

        $this->get(route('admin.media-contents.edit', $mediaContent))
            ->assertOk()
            ->assertSee('stream.example.com · live.mp3')
            ->assertSee('External link')
            ->assertDontSee('File missing from storage');
    }

    public function test_the_edit_form_offers_no_way_to_remove_the_current_stream(): void
    {
        Storage::disk('public')->put('audio/abc123.opus', 'a');
        $mediaContent = $this->mediaContentWithStream('audio/abc123.opus');

        $this->get(route('admin.media-contents.edit', $mediaContent))
            ->assertOk()
            ->assertSee('abc123.opus')
            ->assertDontSee('name="remove_stream"', false);
    }

    public function test_a_save_without_a_new_file_or_link_keeps_the_current_stream_even_if_removal_is_requested(): void
    {
        Storage::disk('public')->put('audio/abc123.opus', 'a');
        $mediaContent = $this->mediaContentWithStream('audio/abc123.opus');

        $this->put(route('admin.media-contents.update', $mediaContent), [
            'category_id' => $mediaContent->category_id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'remove_stream' => '1',
        ])->assertRedirect();

        $this->assertSame('audio/abc123.opus', $mediaContent->fresh()->stream);
        Storage::disk('public')->assertExists('audio/abc123.opus');
    }

    public function test_a_non_media_file_is_rejected_for_stream(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => UploadedFile::fake()->create('not-audio.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('stream');

        $this->assertDatabaseMissing('media_contents', ['slug' => 'qa-track']);
    }

    public function test_replacing_the_stream_keeps_the_old_file_until_the_new_opus_is_ready(): void
    {
        $category = $this->category();
        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload('first.wav'),
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->processPendingAudio($mediaContent);
        $originalStream = $mediaContent->stream;

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload('second.wav'),
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame('processing', $mediaContent->processing_status);
        $this->assertSame($originalStream, $mediaContent->stream);
        Storage::disk('public')->assertExists($originalStream);

        $this->processPendingAudio($mediaContent);

        $this->assertNotSame($originalStream, $mediaContent->stream);
        Storage::disk('public')->assertMissing($originalStream);
        Storage::disk('public')->assertExists($mediaContent->stream);
    }

    public function test_removing_the_thumbnail_clears_it_and_deletes_the_file(): void
    {
        $category = $this->category();
        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 800, 600),
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $originalThumbnail = $mediaContent->thumbnail;

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'remove_thumbnail' => '1',
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertNull($mediaContent->thumbnail);
        Storage::disk('public')->assertMissing($originalThumbnail);
    }

    private function createWithThumbnail(string $slug = 'qa-track'): MediaContent
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => $slug, 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 800, 600),
        ])->assertRedirect();

        return MediaContent::where('slug', $slug)->firstOrFail();
    }

    #[DataProvider('remoteStorages')]
    public function test_a_thumbnail_is_stored_on_the_chosen_remote_storage_and_the_record_remembers_it(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);

        $mediaContent = $this->createWithThumbnail();

        $this->assertSame($disk, $mediaContent->thumbnail_disk);
        Storage::disk($disk)->assertExists($mediaContent->thumbnail);
        Storage::disk('public')->assertMissing($mediaContent->thumbnail);
        $this->assertSame('https://cdn.example.com/'.$mediaContent->thumbnail, $mediaContent->thumbnail_url);
    }

    public function test_private_remote_media_uses_temporary_signed_urls_for_browser_playback(): void
    {
        $this->useRemoteStorage('rustfs', 'media_rustfs');
        config(['filesystems.disks.media_rustfs.temporary_urls' => true]);
        Storage::disk('media_rustfs')->buildTemporaryUrlsUsing(
            fn (string $path) => 'https://storage.example.test/'.$path.'?signed=1',
        );

        $mediaContent = new MediaContent([
            'thumbnail' => 'media-contents/thumbnails/cover.webp',
            'thumbnail_disk' => 'media_rustfs',
            'stream' => 'media-contents/streams/song.opus',
            'stream_disk' => 'media_rustfs',
        ]);

        $this->assertSame(
            'https://storage.example.test/media-contents/thumbnails/cover.webp?signed=1',
            $mediaContent->thumbnail_url,
        );
        $this->assertSame(
            'https://storage.example.test/media-contents/streams/song.opus?signed=1',
            $mediaContent->stream_url,
        );
    }

    public function test_local_media_urls_follow_the_current_request_host(): void
    {
        $mediaContent = MediaContent::create([
            'category_id' => $this->category()->id,
            'title' => 'Local track',
            'slug' => 'local-track',
            'media_type' => 'audio',
            'processing_status' => 'ready',
            'thumbnail' => 'media-contents/thumbnails/cover.webp',
            'stream' => 'media-contents/streams/song.opus',
            'is_published' => false,
        ]);

        app()->instance('request', \Illuminate\Http\Request::create('https://radio.example.test/admin/media-contents'));

        $this->assertSame(
            'https://radio.example.test/storage/'.$mediaContent->thumbnail,
            $mediaContent->thumbnail_url,
        );
        $this->assertSame(
            'https://radio.example.test/storage/'.$mediaContent->stream,
            $mediaContent->stream_url,
        );
    }

    #[DataProvider('remoteStorages')]
    public function test_converted_audio_is_stored_on_the_chosen_remote_storage_while_the_upload_stays_local_until_then(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload(),
        ])->assertRedirect();

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $sourcePath = $mediaContent->source_path;
        // FFmpeg needs a real file, so the upload waits on the local disk for the queue worker.
        Storage::disk('public')->assertExists($sourcePath);

        $this->processPendingAudio($mediaContent);

        $this->assertSame($disk, $mediaContent->stream_disk);
        Storage::disk($disk)->assertExists($mediaContent->stream);
        Storage::disk('public')->assertMissing($mediaContent->stream);
        Storage::disk('public')->assertMissing($sourcePath);
        $this->assertSame('https://cdn.example.com/'.$mediaContent->stream, $mediaContent->stream_url);
        // Cloud files are not probed for their size or presence: that would be a network round trip per row.
        $this->assertNull($mediaContent->stream_size);
        $this->assertSame('Cloud storage', $mediaContent->stream_size_label);
        $this->assertFalse($mediaContent->stream_missing);
    }

    #[DataProvider('remoteStorages')]
    public function test_a_queue_worker_that_booted_before_the_switch_still_uploads_to_the_new_storage(string $driver, string $disk): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload(),
        ])->assertRedirect();
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();

        $this->useRemoteStorage($driver, $disk);
        config(['services.media_storage.disk' => 'public']); // what a worker that started earlier still holds

        $this->processPendingAudio($mediaContent);

        $this->assertSame($disk, $mediaContent->stream_disk);
        Storage::disk($disk)->assertExists($mediaContent->stream);
    }

    #[DataProvider('remoteStorages')]
    public function test_switching_storage_leaves_old_files_where_they_are_and_replacing_one_cleans_up_the_old_disk(string $driver, string $disk): void
    {
        $mediaContent = $this->createWithThumbnail();
        $localThumbnail = $mediaContent->thumbnail;
        $this->assertNull($mediaContent->thumbnail_disk);

        $this->useRemoteStorage($driver, $disk);
        // Still resolves against the local disk after the switch.
        $this->assertStringContainsString('/storage/', $mediaContent->fresh()->thumbnail_url);
        Storage::disk('public')->assertExists($localThumbnail);

        $this->put(route('admin.media-contents.update', $mediaContent), [
            'category_id' => $mediaContent->category_id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame($disk, $mediaContent->thumbnail_disk);
        Storage::disk($disk)->assertExists($mediaContent->thumbnail);
        Storage::disk('public')->assertMissing($localThumbnail);
    }

    #[DataProvider('remoteStorages')]
    public function test_files_already_on_remote_storage_keep_working_after_switching_back_to_local(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        $mediaContent = $this->createWithThumbnail();
        $remoteThumbnail = $mediaContent->thumbnail;

        $this->backToLocalStorage();

        $this->assertSame('https://cdn.example.com/'.$remoteThumbnail, $mediaContent->fresh()->thumbnail_url);

        // Replacing it now writes locally and removes the old one from the disk it was actually on.
        $this->put(route('admin.media-contents.update', $mediaContent), [
            'category_id' => $mediaContent->category_id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'thumbnail' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertNull($mediaContent->thumbnail_disk);
        Storage::disk('public')->assertExists($mediaContent->thumbnail);
        Storage::disk($disk)->assertMissing($remoteThumbnail);
    }

    #[DataProvider('remoteStorages')]
    public function test_removing_a_remote_thumbnail_deletes_it_from_the_remote_disk_and_forgets_the_disk(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        $mediaContent = $this->createWithThumbnail();
        $path = $mediaContent->thumbnail;

        $this->put(route('admin.media-contents.update', $mediaContent), [
            'category_id' => $mediaContent->category_id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'remove_thumbnail' => '1',
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertNull($mediaContent->thumbnail);
        $this->assertNull($mediaContent->thumbnail_disk);
        Storage::disk($disk)->assertMissing($path);
    }

    #[DataProvider('remoteStorages')]
    public function test_pasting_a_stream_url_replaces_a_remote_stream_and_deletes_its_file(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        Storage::disk($disk)->put('audio/remote.opus', 'a');
        $mediaContent = $this->mediaContentWithStream('audio/remote.opus', ['stream_disk' => $disk]);

        $this->put(route('admin.media-contents.update', $mediaContent), [
            'category_id' => $mediaContent->category_id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream_url' => 'https://stream.example.com/live.mp3',
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame('https://stream.example.com/live.mp3', $mediaContent->stream);
        $this->assertNull($mediaContent->stream_disk);
        Storage::disk($disk)->assertMissing('audio/remote.opus');
    }

    #[DataProvider('remoteStorages')]
    public function test_deleting_media_content_removes_its_files_from_the_remote_disk(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        Storage::disk($disk)->put('audio/remote.opus', 'a');
        Storage::disk($disk)->put('images/remote.webp', 'a');
        $mediaContent = $this->mediaContentWithStream('audio/remote.opus', ['stream_disk' => $disk, 'thumbnail' => 'images/remote.webp', 'thumbnail_disk' => $disk]);

        $this->delete(route('admin.media-contents.destroy', $mediaContent))->assertRedirect();

        Storage::disk($disk)->assertMissing('audio/remote.opus');
        Storage::disk($disk)->assertMissing('images/remote.webp');
    }

    public function test_a_record_pointing_at_a_disk_that_is_no_longer_configured_does_not_break_the_page(): void
    {
        $mediaContent = $this->mediaContentWithStream('audio/orphan.opus', ['stream_disk' => 'media_gone']);

        $this->get(route('admin.media-contents.edit', $mediaContent))->assertOk()->assertSee('orphan.opus');
    }

    public function test_deleting_media_content_removes_its_stored_stream_and_thumbnail(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio', 'status' => 'private',
            'stream' => $this->fakeWavUpload(),
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 800, 600),
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->processPendingAudio($mediaContent);
        [$streamPath, $thumbnailPath] = [$mediaContent->stream, $mediaContent->thumbnail];

        $this->delete("/admin/media-contents/{$mediaContent->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($streamPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
    }
}
