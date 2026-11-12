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

    public function test_the_channel_list_is_a_datatable_with_a_strip_of_filters(): void
    {
        $scraped = RadioSceaperStation::create(['name' => 'Scraped FM', 'stream_url' => 'https://stream.example.com/scraped', 'is_published' => true, 'radio_browser_uuid' => 'abc-123']);
        RadioSceaperStation::create(['name' => 'Manual FM', 'stream_url' => 'https://stream.example.com/manual']);
        RadioSceaperStation::create(['name' => 'Draft FM']);
        $day = $scraped->fresh()->created_at->copy()->setTimezone(config('app.admin_timezone'))->format('Y-m-d');

        $html = $this->get('/admin/radio-channels')->assertOk()->getContent();

        // Every channel is on the page (no server paging): the table searches, sorts, filters and pages them.
        $this->assertStringContainsString('<table class="data-table" id="channel-table">', $html);
        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringContainsString("jQuery('#channel-table').DataTable(", $html);
        $this->assertStringContainsString('targets: 4, orderable: false, searchable: false', $html);   // the Actions column (a hidden Position column follows it)
        $this->assertStringContainsString('order: [[POSITION, \'asc\']]', $html);
        $this->assertSame(3, substr_count($html, 'class="drag-handle" data-id="'));   // a grip on each row, for an admin who may edit
        $this->assertStringContainsString("emptyTable: 'No channels yet. Sync from Radio Browser or add one manually.'", $html);
        $this->assertStringContainsString("attachTableFilters(table, { filtersId: 'channel-filters', dateKey: 'added', searchLabel: 'Search' });", $html);

        // The strip: Status and Source, with Clear. No date boxes (the Added column is still there to sort by).
        $this->assertMatchesRegularExpression('/<div class="table-filters" id="channel-filters"[^>]*>\s*<label class="filter-field"><span>Status<\/span>\s*<span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"><\/i>\s*<select data-filter="status">\s*<option value="">All statuses<\/option>\s*<option value="published">Published<\/option>\s*<option value="hidden">Hidden<\/option>\s*<option value="draft">Draft<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<label class="filter-field"><span>Source<\/span>\s*<span class="filter-control"><i class="fa fa-globe" aria-hidden="true"><\/i>\s*<select data-filter="source">\s*<option value="">All sources<\/option>\s*<option value="radio-browser">Radio Browser<\/option>\s*<option value="manual">Added manually<\/option>/', $html);
        $this->assertMatchesRegularExpression('/data-filter-clear title="Clear filters" aria-label="Clear filters" hidden>/', $html);
        $this->assertStringNotContainsString('type="date"', $html);
        $this->assertStringNotContainsString('data-filter="from"', $html);
        $this->assertStringContainsString('<th>Added</th>', $html);
        $this->assertMatchesRegularExpression('/<thead>\s*<tr>\s*<th>Name<\/th>\s*<th>Stream URL<\/th>/', $html);
        $this->assertStringNotContainsString('Sort order', $html);   // the order is set by dragging rows, not shown as a column

        // Each row carries what the filters read: its status, where it came from and the day it was added.
        $this->assertStringContainsString('<tr data-status="published" data-source="radio-browser" data-added="'.$day.'">', $html);
        $this->assertStringContainsString('<tr data-status="hidden" data-source="manual" data-added="'.$day.'">', $html);
        $this->assertStringContainsString('<tr data-status="draft" data-source="manual" data-added="'.$day.'">', $html);

        // Responsive: every value cell names its column for the card layout on phones, and the shared styles are in.
        foreach (['Stream URL', 'Status', 'Added'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html, $label);
        }
        $this->assertStringContainsString('#channel-table { min-width: 1000px; }', $html);
        $this->assertStringContainsString('@media (max-width: 760px)', $html);

        // With no channels there is nothing to filter, but the table still says so itself.
        RadioSceaperStation::query()->delete();
        $empty = $this->get('/admin/radio-channels')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="channel-filters"', $empty);
        $this->assertStringContainsString("jQuery('#channel-table').DataTable(", $empty);
    }

    public function test_the_cover_guidance_uses_a_four_by_three_ratio(): void
    {
        $this->get('/admin/radio-channels/create')
            ->assertOk()
            ->assertSee('— kept whole as WebP, never cropped (4:3 ratio)');

        app()->setLocale('km');
        $this->assertSame(
            '— រក្សាទុកទាំងមូលជា WebP មិនកាត់ទេ (Ratio 4:3)',
            __('— kept whole as WebP, never cropped (4:3 ratio)'),
        );
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

    public function test_the_radio_channels_pages_say_content_over_their_title(): void
    {
        $channel = RadioSceaperStation::create(['name' => 'Pulse FM', 'stream_url' => 'https://stream.example.com/pulse']);

        // The same label as the sidebar group Radio Channels sits in (it used to say "Network").
        foreach (['/admin/radio-channels', '/admin/radio-channels/create', "/admin/radio-channels/{$channel->id}", "/admin/radio-channels/{$channel->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<p class="eyebrow">Content<\/p>/', $html, $url);
            $this->assertStringNotContainsString('<p class="eyebrow">Network</p>', $html, $url);
        }
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

    public function test_the_form_does_not_set_the_order_that_is_only_set_by_dragging(): void
    {
        // A new channel starts unnumbered (0), below the numbered ones, even if a number is sent.
        $this->post('/admin/radio-channels', ['name' => 'Ordered Radio', 'sort_order' => '3'])
            ->assertRedirect(route('admin.radio-channels.index'));
        $channel = RadioSceaperStation::where('name', 'Ordered Radio')->firstOrFail();
        $this->assertSame(0, $channel->sort_order);

        // Saving the form leaves a channel where it was placed, even if a stale page sends a number.
        $channel->update(['sort_order' => 3]);
        $this->put("/admin/radio-channels/{$channel->id}", ['name' => 'Ordered Radio', 'sort_order' => '9'])->assertRedirect();
        $this->assertSame(3, $channel->fresh()->sort_order);

        $this->get("/admin/radio-channels/{$channel->id}/edit")->assertOk()->assertDontSee('name="sort_order"', false);
    }

    public function test_numbered_channels_come_first_then_the_unset_ones_by_id_in_the_api_and_the_admin_list(): void
    {
        $make = fn (string $name, int $sortOrder) => RadioSceaperStation::create([
            'name' => $name, 'stream_url' => 'https://stream.example.com/'.strtolower($name), 'is_published' => true, 'sort_order' => $sortOrder,
        ]);
        $make('Added 1st, unset', 0);
        $make('Added 2nd, order 5', 5);
        $make('Added 3rd, order 1', 1);
        $make('Added 4th, unset', 0);
        $make('Added 5th, order 5', 5);
        $expected = ['Added 3rd, order 1', 'Added 2nd, order 5', 'Added 5th, order 5', 'Added 1st, unset', 'Added 4th, unset'];

        $this->assertSame($expected, $this->getJson('/api/stations')->assertOk()->json('data.*.name'));
        $this->assertSame($expected, $this->getJson('/api/home')->assertOk()->json('data.stations.*.name'));
        $this->get('/admin/radio-channels')->assertOk()->assertSeeInOrder($expected);
    }

    public function test_dragging_a_row_saves_its_new_place_in_the_list(): void
    {
        $make = fn (string $name, int $sortOrder = 0) => RadioSceaperStation::create(['name' => $name, 'sort_order' => $sortOrder]);
        [$a, $b, $c, $d] = [$make('A'), $make('B'), $make('C'), $make('D')];
        $e = $make('E', 1);
        $order = fn () => RadioSceaperStation::inListenerOrder()->pluck('name')->all();
        $this->assertSame(['E', 'A', 'B', 'C', 'D'], $order());

        // D dropped above A: it lands there, and the list (the unset ones too) is numbered 1, 2, 3… from the top.
        $this->patchJson('/admin/radio-channels/reorder', ['id' => $d->id, 'target' => $a->id, 'placement' => 'before'])->assertNoContent();
        $this->assertSame(['E', 'D', 'A', 'B', 'C'], $order());
        $this->assertSame([1, 2, 3, 4, 5], RadioSceaperStation::inListenerOrder()->pluck('sort_order')->all());

        // E dropped below B (the last row on the page is placed after its neighbour).
        $this->patchJson('/admin/radio-channels/reorder', ['id' => $e->id, 'target' => $b->id, 'placement' => 'after'])->assertNoContent();
        $this->assertSame(['D', 'A', 'B', 'E', 'C'], $order());
        $this->assertSame([1, 2, 3, 4, 5], RadioSceaperStation::inListenerOrder()->pluck('sort_order')->all());

        // A row can't be dropped on itself, and both rows must exist.
        $this->patchJson('/admin/radio-channels/reorder', ['id' => $c->id, 'target' => $c->id, 'placement' => 'before'])->assertJsonValidationErrors('target');
        $this->patchJson('/admin/radio-channels/reorder', ['id' => $c->id, 'target' => 9999, 'placement' => 'before'])->assertJsonValidationErrors('target');
        $this->patchJson('/admin/radio-channels/reorder', ['id' => $c->id, 'target' => $a->id, 'placement' => 'beside'])->assertJsonValidationErrors('placement');
        $this->assertSame(['D', 'A', 'B', 'E', 'C'], $order());
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
