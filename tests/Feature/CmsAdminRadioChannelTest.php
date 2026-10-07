<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\RadioSceaper\Models\RadioSceaperStation;
use Src\Setting\Models\StorageSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\UsesRemoteStorage;
use Tests\TestCase;

class CmsAdminRadioChannelTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase, UsesRemoteStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs($this->createAdmin([
            'view-radio-channels', 'create-radio-channels', 'edit-radio-channels', 'delete-radio-channels',
            'view-dashboard',
        ]), 'admin');
    }

    public function test_index_lists_channels(): void
    {
        RadioSceaperStation::create([
            'name' => 'Pulse FM',
            'stream_url' => 'https://stream.example.com/pulse',
            'votes' => 5,
            'is_published' => false,
        ]);

        $this->get('/admin/radio-channels')->assertOk()->assertSee('Pulse FM');
    }

    public function test_sync_pulls_new_stations(): void
    {
        Http::fake([
            'de1.api.radio-browser.info/*' => Http::response([
                [
                    'stationuuid' => 'aaa',
                    'name' => 'Pulse FM',
                    'url_resolved' => 'https://stream.example.com/pulse',
                    'bitrate' => 128,
                    'votes' => 10,
                    'tags' => 'pop',
                    'lastcheckok' => 1,
                ],
            ]),
        ]);

        $this->post('/admin/radio-channels/sync')->assertRedirect();

        $this->assertSame(1, RadioSceaperStation::count());
    }

    public function test_toggle_publish_flips_visibility(): void
    {
        $channel = RadioSceaperStation::create([
            'name' => 'Pulse FM',
            'stream_url' => 'https://stream.example.com/pulse',
            'is_published' => false,
        ]);

        $this->patch("/admin/radio-channels/{$channel->id}/toggle-publish")->assertRedirect();

        $this->assertTrue($channel->fresh()->is_published);
    }

    public function test_destroy_removes_channel(): void
    {
        $channel = RadioSceaperStation::create([
            'name' => 'Pulse FM',
            'stream_url' => 'https://stream.example.com/pulse',
            'is_published' => false,
        ]);

        $this->delete("/admin/radio-channels/{$channel->id}")->assertRedirect();

        $this->assertSame(0, RadioSceaperStation::count());
    }

    public function test_it_creates_a_channel_with_a_stream_url(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'QA Radio',
            'stream_url' => 'https://stream.example.com/qa',
            'bitrate' => 128,
        ])->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'QA Radio')->firstOrFail();
        $this->assertSame('hidden', $channel->status);
    }

    public function test_it_creates_a_draft_channel_with_no_stream_url(): void
    {
        $this->post('/admin/radio-channels', ['name' => 'QA Draft Radio'])
            ->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'QA Draft Radio')->firstOrFail();
        $this->assertNull($channel->stream_url);
        $this->assertSame('draft', $channel->status);
    }

    public function test_a_published_channel_with_a_stream_url_has_published_status(): void
    {
        $channel = RadioSceaperStation::create([
            'name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse', 'is_published' => true,
        ]);

        $this->assertSame('published', $channel->status);
    }

    public function test_it_rejects_a_duplicate_stream_url(): void
    {
        RadioSceaperStation::create(['name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse']);

        $this->post('/admin/radio-channels', [
            'name' => 'Copycat FM',
            'stream_url' => 'https://stream.example.com/pulse',
        ])->assertSessionHasErrors('stream_url');
    }

    public function test_it_updates_a_channel(): void
    {
        $channel = RadioSceaperStation::create(['name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse']);

        $this->put("/admin/radio-channels/{$channel->id}", [
            'name' => 'Pulse FM Renamed',
            'stream_url' => 'https://stream.example.com/pulse',
        ])->assertRedirect(route('admin.radio-channels.index'));

        $this->assertSame('Pulse FM Renamed', $channel->fresh()->name);
    }

    public function test_check_signal_reports_a_reachable_stream(): void
    {
        $channel = RadioSceaperStation::create(['name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse']);
        Http::fake(['stream.example.com/*' => Http::response('', 200)]);

        $response = $this->post("/admin/radio-channels/{$channel->id}/check-signal")->assertRedirect();

        $this->assertStringContainsString('reachable', session('status'));
    }

    public function test_check_signal_reports_an_unreachable_stream(): void
    {
        $channel = RadioSceaperStation::create(['name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse']);
        Http::fake(['stream.example.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->post("/admin/radio-channels/{$channel->id}/check-signal")->assertRedirect();

        $this->assertStringContainsString('unreachable', session('error'));
    }

    public function test_check_signal_refuses_a_channel_with_no_stream_url(): void
    {
        $channel = RadioSceaperStation::create(['name' => 'QA Draft Radio']);

        $this->post("/admin/radio-channels/{$channel->id}/check-signal")->assertRedirect();

        $this->assertStringContainsString('no stream URL', session('error'));
    }

    public function test_show_page_displays_channel_details(): void
    {
        $channel = RadioSceaperStation::create([
            'name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse', 'bitrate' => 128,
        ]);

        $this->get("/admin/radio-channels/{$channel->id}")
            ->assertOk()
            ->assertSee('Pulse FM')
            ->assertSee('128 kbps');
    }

    public function test_uploading_a_cover_keeps_the_whole_picture_and_converts_it_to_webp(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'Cover Radio',
            'cover' => UploadedFile::fake()->image('cover.jpg', 2000, 500),
        ])->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'Cover Radio')->firstOrFail();
        $this->assertStringEndsWith('.webp', $channel->cover);

        // 2000×500 is scaled down to fit 1280 px with its own proportions — not cut to a strip.
        $image = imagecreatefromstring(Storage::disk('public')->get($channel->cover));
        $this->assertSame([1280, 320], [imagesx($image), imagesy($image)]);

        $bytes = Storage::disk('public')->get($channel->cover);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
    }

    public function test_checking_remove_cover_clears_it_and_deletes_the_file(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'Cover Radio',
            'cover' => UploadedFile::fake()->image('cover.jpg', 2000, 500),
        ]);
        $channel = RadioSceaperStation::where('name', 'Cover Radio')->firstOrFail();
        $originalCover = $channel->cover;

        $this->put("/admin/radio-channels/{$channel->id}", [
            'name' => 'Cover Radio',
            'remove_cover' => '1',
        ])->assertRedirect();

        $channel->refresh();
        $this->assertNull($channel->cover);
        Storage::disk('public')->assertMissing($originalCover);
    }

    public function test_deleting_a_channel_removes_its_stored_cover(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'Cover Radio',
            'cover' => UploadedFile::fake()->image('cover.jpg', 2000, 500),
        ]);
        $channel = RadioSceaperStation::where('name', 'Cover Radio')->firstOrFail();
        $coverPath = $channel->cover;

        $this->delete("/admin/radio-channels/{$channel->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($coverPath);
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

    public function test_a_pasted_cover_url_is_downloaded_whole_and_converted_to_webp(): void
    {
        Http::fake(['cdn.example.com/*' => Http::response($this->fakeImageBytes(2000, 500), 200)]);

        $this->post('/admin/radio-channels', [
            'name' => 'URL Cover Radio',
            'cover_url' => 'https://cdn.example.com/cover.png',
        ])->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'URL Cover Radio')->firstOrFail();
        $this->assertStringEndsWith('.webp', $channel->cover);

        $image = imagecreatefromstring(Storage::disk('public')->get($channel->cover));
        $this->assertSame([1280, 320], [imagesx($image), imagesy($image)]);
    }

    public function test_a_cover_smaller_than_the_limit_keeps_its_exact_size_and_shape(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'Portrait Radio',
            'cover' => UploadedFile::fake()->image('cover.jpg', 600, 900),
        ])->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'Portrait Radio')->firstOrFail();

        $image = imagecreatefromstring(Storage::disk('public')->get($channel->cover));
        $this->assertSame([600, 900], [imagesx($image), imagesy($image)]);
    }

    public function test_a_cover_url_that_fails_to_download_is_rejected(): void
    {
        Http::fake(['cdn.example.com/*' => Http::response('', 404)]);

        // Rejected as an error on the cover URL field (it used to be a bare 422 page), and nothing is saved.
        $this->post('/admin/radio-channels', [
            'name' => 'Broken Cover Radio',
            'cover_url' => 'https://cdn.example.com/missing.png',
        ])->assertSessionHasErrors(['cover_url' => 'Could not download that image URL.']);

        $this->assertDatabaseMissing('radio_sceaper_stations', ['name' => 'Broken Cover Radio']);
    }

    private function createChannelWithCover(string $name = 'Cover Radio'): RadioSceaperStation
    {
        $this->post('/admin/radio-channels', [
            'name' => $name,
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ])->assertRedirect(route('admin.radio-channels.index'));

        return RadioSceaperStation::where('name', $name)->firstOrFail();
    }

    public function test_a_cover_uploaded_with_local_storage_has_no_disk_and_lands_in_the_image_folder(): void
    {
        $channel = $this->createChannelWithCover();

        $this->assertNull($channel->cover_disk);
        $this->assertStringStartsWith('media-contents/thumbnails/', $channel->cover);
        Storage::disk('public')->assertExists($channel->cover);
        $this->assertStringContainsString('/storage/', $channel->cover_url);
    }

    public function test_the_admin_configured_image_folder_is_used_for_covers(): void
    {
        StorageSetting::firstOrFail()->update(['image_directory' => 'custom/images']);
        StorageSetting::firstOrFail()->applyToConfig();

        $channel = $this->createChannelWithCover();

        $this->assertStringStartsWith('custom/images/', $channel->cover);
    }

    #[DataProvider('remoteStorages')]
    public function test_a_cover_is_stored_on_the_chosen_remote_storage_and_the_channel_remembers_it(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);

        $channel = $this->createChannelWithCover();

        $this->assertSame($disk, $channel->cover_disk);
        Storage::disk($disk)->assertExists($channel->cover);
        Storage::disk('public')->assertMissing($channel->cover);
        $this->assertSame('https://cdn.example.com/'.$channel->cover, $channel->cover_url);
    }

    #[DataProvider('remoteStorages')]
    public function test_a_pasted_cover_url_is_stored_on_the_chosen_remote_storage(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        Http::fake(['cdn.example.com/*' => Http::response($this->fakeImageBytes(800, 600), 200)]);

        $this->post('/admin/radio-channels', [
            'name' => 'URL Cover Radio',
            'cover_url' => 'https://cdn.example.com/cover.png',
        ])->assertRedirect();

        $channel = RadioSceaperStation::where('name', 'URL Cover Radio')->firstOrFail();
        $this->assertSame($disk, $channel->cover_disk);
        Storage::disk($disk)->assertExists($channel->cover);
    }

    #[DataProvider('remoteStorages')]
    public function test_switching_storage_leaves_old_covers_where_they_are_and_replacing_one_cleans_up_the_old_disk(string $driver, string $disk): void
    {
        $channel = $this->createChannelWithCover();
        $localCover = $channel->cover;

        $this->useRemoteStorage($driver, $disk);
        // Still resolves against the local disk after the switch.
        $this->assertStringContainsString('/storage/', $channel->fresh()->cover_url);
        Storage::disk('public')->assertExists($localCover);

        $this->put("/admin/radio-channels/{$channel->id}", [
            'name' => 'Cover Radio',
            'cover' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ])->assertRedirect();

        $channel->refresh();
        $this->assertSame($disk, $channel->cover_disk);
        Storage::disk($disk)->assertExists($channel->cover);
        Storage::disk('public')->assertMissing($localCover);
    }

    #[DataProvider('remoteStorages')]
    public function test_saving_without_a_new_cover_keeps_the_cover_and_the_disk_it_lives_on(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        $channel = $this->createChannelWithCover();
        $this->backToLocalStorage();

        $this->put("/admin/radio-channels/{$channel->id}", ['name' => 'Renamed Radio'])->assertRedirect();

        $channel->refresh();
        $this->assertSame($disk, $channel->cover_disk);
        Storage::disk($disk)->assertExists($channel->cover);
        $this->assertSame('https://cdn.example.com/'.$channel->cover, $channel->cover_url);
    }

    #[DataProvider('remoteStorages')]
    public function test_removing_or_deleting_a_remote_cover_deletes_the_file_from_that_disk(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);
        $removed = $this->createChannelWithCover('Removed Cover Radio');
        $deleted = $this->createChannelWithCover('Deleted Cover Radio');
        $removedCover = $removed->cover;
        $deletedCover = $deleted->cover;
        $this->backToLocalStorage();

        $this->put("/admin/radio-channels/{$removed->id}", ['name' => 'Removed Cover Radio', 'remove_cover' => '1'])->assertRedirect();
        $this->delete("/admin/radio-channels/{$deleted->id}")->assertRedirect();

        $removed->refresh();
        $this->assertNull($removed->cover);
        $this->assertNull($removed->cover_disk);
        Storage::disk($disk)->assertMissing($removedCover);
        Storage::disk($disk)->assertMissing($deletedCover);
    }

    public function test_it_saves_and_shows_a_channel_description(): void
    {
        $this->post('/admin/radio-channels', [
            'name' => 'Described Radio',
            'description' => 'A 24/7 khmer pop station broadcasting from Phnom Penh.',
        ])->assertRedirect(route('admin.radio-channels.index'));

        $channel = RadioSceaperStation::where('name', 'Described Radio')->firstOrFail();
        $this->assertSame('A 24/7 khmer pop station broadcasting from Phnom Penh.', $channel->description);

        $this->get("/admin/radio-channels/{$channel->id}")->assertSee('A 24/7 khmer pop station');
    }
}
