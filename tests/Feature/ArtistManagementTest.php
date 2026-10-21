<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Models\Artist;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\UsesRemoteStorage;
use Tests\TestCase;

class ArtistManagementTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase, UsesRemoteStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs($this->createAdmin(['view-artists', 'create-artists', 'edit-artists', 'delete-artists']), 'admin');
    }

    private function assertIsWebp(string $path): void
    {
        $bytes = Storage::disk('public')->get($path);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
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

    private function dimensionsOf(string $path): array
    {
        $image = imagecreatefromstring(Storage::disk('public')->get($path));

        return [imagesx($image), imagesy($image)];
    }

    public function test_index_lists_artists(): void
    {
        Artist::create(['name' => 'Sokun Nisa', 'slug' => 'sokun-nisa']);

        $this->get('/admin/artists')->assertOk()->assertSee('Sokun Nisa');
    }

    public function test_the_artist_list_is_a_datatable_with_a_strip_of_filters(): void
    {
        Artist::create(['name' => 'Far Star', 'slug' => 'far-star', 'region' => 'international', 'verified' => true]);
        Artist::create(['name' => 'Near Star', 'slug' => 'near-star', 'region' => 'local']);
        Artist::create(['name' => 'Nowhere', 'slug' => 'nowhere', 'stage_name' => 'No Region Yet']);

        $html = $this->get('/admin/artists')->assertOk()->getContent();

        // Every artist is on the page (no server paging): the table searches, sorts, filters and pages them.
        $this->assertStringContainsString('<table class="data-table" id="artist-table">', $html);
        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringContainsString("jQuery('#artist-table').DataTable(", $html);
        $this->assertStringContainsString('targets: -1, orderable: false, searchable: false', $html);
        $this->assertStringContainsString("emptyTable: 'No artists yet.'", $html);
        $this->assertStringContainsString("attachTableFilters(table, { filtersId: 'artist-filters', searchLabel: 'Search' });", $html);

        // The strip: Active and Region, with Clear. No date boxes.
        $this->assertMatchesRegularExpression('/<div class="table-filters" id="artist-filters"[^>]*>\s*<label class="filter-field"><span>Active<\/span>\s*<span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"><\/i>\s*<select data-filter="active">\s*<option value="">All<\/option>\s*<option value="active">Active<\/option>\s*<option value="inactive">Inactive<\/option>\s*<\/select>/', $html);
        $this->assertMatchesRegularExpression('/<label class="filter-field"><span>Region<\/span>\s*<span class="filter-control"><i class="fa fa-globe" aria-hidden="true"><\/i>\s*<select data-filter="region">\s*<option value="">All regions<\/option>\s*<option value="international">International<\/option>\s*<option value="local">Local<\/option>\s*<option value="none">Not set<\/option>\s*<\/select>/', $html);
        $this->assertMatchesRegularExpression('/data-filter-clear title="Clear filters" aria-label="Clear filters" hidden>/', $html);
        $this->assertStringNotContainsString('data-filter="from"', $html);

        // Each row carries what the filters read; the name sorts by what is shown (the stage name where there is one).
        $this->assertStringContainsString('<tr data-active="active" data-region="international">', $html);
        $this->assertStringContainsString('<tr data-active="inactive" data-region="local">', $html);
        $this->assertStringContainsString('<tr data-active="inactive" data-region="none">', $html);
        $this->assertStringContainsString('data-order="No Region Yet"', $html);

        // Responsive: the value cells name their column for the card layout on phones, and the shared styles are in.
        foreach (['Active', 'Region'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html, $label);
        }
        $this->assertStringContainsString('@media (max-width: 760px)', $html);

        // Nothing to filter with no artists, but the table still says so itself.
        Artist::query()->delete();
        $empty = $this->get('/admin/artists')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="artist-filters"', $empty);
        $this->assertStringContainsString("jQuery('#artist-table').DataTable(", $empty);
    }

    public function test_it_creates_an_artist_with_minimal_fields(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Preap Sovath',
            'slug' => 'preap-sovath',
            'status' => 'active',
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'preap-sovath')->firstOrFail();
        $this->assertFalse($artist->verified);
        $this->assertSame([], $artist->social_links);
        $this->assertNull($artist->photo);
    }

    public function test_the_verified_switch_is_worded_active_on_the_form_and_in_the_list(): void
    {
        $on = Artist::create(['name' => 'Switched On', 'slug' => 'switched-on', 'status' => 'active', 'verified' => true]);
        $off = Artist::create(['name' => 'Switched Off', 'slug' => 'switched-off', 'status' => 'active', 'verified' => false]);

        // The form: the same switch (still the artist's `verified`), now called Active.
        foreach (['/admin/artists/create', "/admin/artists/{$on->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<span><b>Active<\/b><small>Shows an active badge on the artist(?:\'|&#039;)s profile\.<\/small><\/span>\s*<span class="switch"><input type="checkbox" name="verified"/', $form, $url);
        }
        $this->assertMatchesRegularExpression('/name="verified" value="1" checked/', $this->get("/admin/artists/{$on->id}/edit")->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="verified" value="1" checked/', $this->get("/admin/artists/{$off->id}/edit")->getContent());

        // The list: an Active column, "Active" with the tick for a switched-on artist and "Inactive" for the rest.
        $list = $this->get('/admin/artists')->assertOk()->getContent();
        $this->assertStringContainsString('<th>Active</th>', $list);
        $this->assertSame(1, substr_count($list, '<span class="badge green"><i class="fa fa-check"></i> Active</span>'));
        $this->assertSame(1, substr_count($list, '<span class="badge slate">Inactive</span>'));

        // The word is gone everywhere.
        foreach ([$list, $this->get('/admin/artists/create')->getContent()] as $page) {
            $this->assertStringNotContainsString('Verified', $page);
            $this->assertStringNotContainsString('Unverified', $page);
        }

        // Saving it works as it did.
        $this->post('/admin/artists', ['name' => 'New One', 'slug' => 'new-one', 'status' => 'active', 'verified' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(Artist::where('slug', 'new-one')->firstOrFail()->verified);
        $this->put("/admin/artists/{$on->id}", ['name' => 'Switched On', 'slug' => 'switched-on', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertFalse($on->fresh()->verified);
    }

    public function test_active_is_translated_into_khmer_on_the_form_and_in_the_list(): void
    {
        Artist::create(['name' => 'Switched On', 'slug' => 'switched-on', 'status' => 'active', 'verified' => true]);
        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect();

        $form = $this->get('/admin/artists/create')->assertOk()->getContent();
        $this->assertStringContainsString('<span><b>សកម្ម</b><small>បង្ហាញសញ្ញាសកម្មនៅលើប្រវត្តិរូបសិល្បករ។</small></span>', $form);

        $list = $this->get('/admin/artists')->assertOk()->getContent();
        $this->assertStringContainsString('<th>សកម្ម</th>', $list);
        $this->assertStringNotContainsString('បានផ្ទៀងផ្ទាត់', $list);
    }

    public function test_the_300x300_picture_is_called_profile_on_the_form(): void
    {
        $artist = Artist::create(['name' => 'Some Artist', 'slug' => 'some-artist', 'status' => 'active']);

        foreach (['/admin/artists/create', "/admin/artists/{$artist->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();

            // "Profile — cropped to WebP, 300×300" and "Cover — …": the first label of the two image fields.
            $this->assertMatchesRegularExpression('/<label>Profile <small[^>]*>— cropped to WebP, 300×300<\/small>/', $form, $url);
            $this->assertStringNotContainsString('<label>Photo', $form, $url);
            // Only the label changed: the field is still the artist's `photo`.
            $this->assertStringContainsString('data-image-field="photo"', $form, $url);
            $this->assertMatchesRegularExpression('/<input id="photo" name="photo" type="file"|name="photo" type="file"/', $form, $url);
        }
    }

    public function test_a_profile_picture_that_is_not_an_image_says_profile_in_the_message(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Some Artist', 'slug' => 'some-artist', 'status' => 'active',
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $message = session('errors')->first('photo');
        $this->assertStringContainsString('profile', $message);
        $this->assertStringNotContainsString('photo', $message);

        $this->post('/admin/artists', [
            'name' => 'Some Artist', 'slug' => 'some-artist', 'status' => 'active', 'photo_url' => 'not a url',
        ])->assertSessionHasErrors('photo_url');
        $this->assertSame('The profile URL field must be a valid URL.', session('errors')->first('photo_url'));
    }

    public function test_profile_is_translated_into_khmer_and_does_not_clash_with_bio(): void
    {
        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect();

        $form = $this->get('/admin/artists/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<label>រូបតំណាង <small/', $form);
        $this->assertStringNotContainsString('<label>Profile', $form);
        // "Bio" keeps its own Khmer word (ប្រវត្តិរូប), so the two labels on the form are different words.
        $this->assertSame('រូបតំណាង', trans('Profile', [], 'km'));
        $this->assertNotSame(trans('Bio', [], 'km'), trans('Profile', [], 'km'));
        $this->assertNotSame('Bio', trans('Bio', [], 'km'), 'Bio has a Khmer translation to differ from');
    }

    public function test_uploading_a_photo_is_cropped_to_300x300_and_converted_to_webp(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Photo Artist',
            'slug' => 'photo-artist',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'photo-artist')->firstOrFail();
        $this->assertStringEndsWith('.webp', $artist->photo);
        $this->assertSame([300, 300], $this->dimensionsOf($artist->photo));
        $this->assertIsWebp($artist->photo);
        $this->assertStringContainsString('/storage/', $artist->photo_url);
    }

    #[DataProvider('remoteStorages')]
    public function test_photo_and_cover_are_stored_on_the_chosen_remote_storage_and_the_artist_remembers_it(string $driver, string $disk): void
    {
        $this->useRemoteStorage($driver, $disk);

        $this->post('/admin/artists', [
            'name' => 'Cloud Artist',
            'slug' => 'cloud-artist',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'cloud-artist')->firstOrFail();
        $this->assertSame($disk, $artist->photo_disk);
        $this->assertSame($disk, $artist->cover_disk);
        Storage::disk($disk)->assertExists($artist->photo);
        Storage::disk($disk)->assertExists($artist->cover);
        Storage::disk('public')->assertMissing($artist->photo);
        $this->assertSame('https://cdn.example.com/'.$artist->photo, $artist->photo_url);
        $this->assertSame('https://cdn.example.com/'.$artist->cover, $artist->cover_url);

        $photo = $artist->photo;
        $cover = $artist->cover;
        $this->backToLocalStorage();

        $this->delete("/admin/artists/{$artist->id}")->assertRedirect();

        Storage::disk($disk)->assertMissing($photo);
        Storage::disk($disk)->assertMissing($cover);
    }

    public function test_a_pasted_photo_url_is_downloaded_cropped_and_converted_to_webp(): void
    {
        Http::fake(['cdn.example.com/*' => Http::response($this->fakeImageBytes(800, 600), 200)]);

        $this->post('/admin/artists', [
            'name' => 'URL Photo Artist',
            'slug' => 'url-photo-artist',
            'status' => 'active',
            'photo_url' => 'https://cdn.example.com/photo.png',
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'url-photo-artist')->firstOrFail();
        $this->assertStringEndsWith('.webp', $artist->photo);
        $this->assertSame([300, 300], $this->dimensionsOf($artist->photo));
        $this->assertIsWebp($artist->photo);
    }

    public function test_uploading_a_cover_is_cropped_to_1200x400_and_converted_to_webp(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Cover Artist',
            'slug' => 'cover-artist',
            'status' => 'active',
            'cover' => UploadedFile::fake()->image('cover.png', 2000, 500),
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'cover-artist')->firstOrFail();
        $this->assertSame([1200, 400], $this->dimensionsOf($artist->cover));
        $this->assertIsWebp($artist->cover);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Bad Upload',
            'slug' => 'bad-upload',
            'status' => 'active',
            'photo' => UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('artists', ['slug' => 'bad-upload']);
    }

    public function test_it_rejects_an_invalid_status(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Bad Status',
            'slug' => 'bad-status',
            'status' => 'deleted-forever',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('artists', ['slug' => 'bad-status']);
    }

    public function test_the_form_offers_a_region_where_it_used_to_offer_a_status(): void
    {
        $artist = Artist::create(['name' => 'Sokun Nisa', 'slug' => 'sokun-nisa', 'region' => 'local']);

        foreach (['/admin/artists/create', "/admin/artists/{$artist->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<label>Region\s*<select id="region" name="region">\s*<option value="">Select Region<\/option>\s*<option value="international"[^>]*>International<\/option>\s*<option value="local"[^>]*>Local<\/option>/', $html, $url);
            $this->assertStringNotContainsString('id="status"', $html, $url);
        }

        // An artist's own region is the one picked on its edit form.
        $this->get("/admin/artists/{$artist->id}/edit")->assertSee('<option value="local" selected>Local</option>', false);
    }

    public function test_the_region_is_saved_and_the_status_is_left_alone(): void
    {
        // A new artist gets the region it was given and the column's default status, though the form sends none.
        $this->post('/admin/artists', ['name' => 'Far Away', 'slug' => 'far-away', 'region' => 'international'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.artists.index'));
        $created = Artist::where('slug', 'far-away')->firstOrFail();
        $this->assertSame('international', $created->region);
        $this->assertSame('active', $created->fresh()->status);

        // An edit changes the region and keeps a status set some other way.
        $artist = Artist::create(['name' => 'Nearby', 'slug' => 'nearby', 'status' => 'inactive']);
        $this->assertNull($artist->fresh()->region);
        $this->put("/admin/artists/{$artist->id}", ['name' => 'Nearby', 'slug' => 'nearby', 'region' => 'local'])->assertSessionHasNoErrors();
        $this->assertSame('local', $artist->fresh()->region);
        $this->assertSame('inactive', $artist->fresh()->status);

        // Leaving it unpicked is fine (the artists that exist have none), and the list says which each one has.
        $this->put("/admin/artists/{$artist->id}", ['name' => 'Nearby', 'slug' => 'nearby', 'region' => ''])->assertSessionHasNoErrors();
        $this->assertNull($artist->fresh()->region);
        $this->get('/admin/artists')->assertOk()->assertSee('<th>Region</th>', false)->assertSee('International');
    }

    public function test_it_rejects_an_unknown_region(): void
    {
        $this->post('/admin/artists', ['name' => 'Nowhere', 'slug' => 'nowhere', 'region' => 'moon'])->assertSessionHasErrors('region');

        $this->assertDatabaseMissing('artists', ['slug' => 'nowhere']);
    }

    public function test_it_rejects_a_duplicate_slug(): void
    {
        Artist::create(['name' => 'Existing', 'slug' => 'dup-slug']);

        $this->post('/admin/artists', [
            'name' => 'Another',
            'slug' => 'dup-slug',
            'status' => 'active',
        ])->assertSessionHasErrors('slug');
    }

    public function test_it_creates_a_fully_populated_artist_with_social_links(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Full Name',
            'slug' => 'full-artist',
            'stage_name' => 'Stage Name',
            'bio' => 'A short bio.',
            'status' => 'active',
            'verified' => '1',
            'social_links' => [
                'facebook' => 'https://facebook.com/fullartist',
                'instagram' => '',
                'tiktok' => 'https://tiktok.com/@fullartist',
                'youtube' => '',
            ],
        ])->assertRedirect(route('admin.artists.index'));

        $artist = Artist::where('slug', 'full-artist')->firstOrFail();
        $this->assertTrue($artist->verified);
        $this->assertSame([
            'facebook' => 'https://facebook.com/fullartist',
            'tiktok' => 'https://tiktok.com/@fullartist',
        ], $artist->social_links);
    }

    public function test_it_updates_an_artist_without_touching_its_existing_photo(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Old Name', 'slug' => 'update-me', 'status' => 'active',
            'photo' => UploadedFile::fake()->image('photo.jpg', 500, 500),
        ]);
        $artist = Artist::where('slug', 'update-me')->firstOrFail();
        $originalPhoto = $artist->photo;

        $this->put("/admin/artists/{$artist->id}", [
            'name' => 'New Name',
            'slug' => 'update-me',
            'status' => 'inactive',
        ])->assertRedirect(route('admin.artists.index'));

        $artist->refresh();
        $this->assertSame('New Name', $artist->name);
        $this->assertSame('inactive', $artist->status);
        $this->assertSame($originalPhoto, $artist->photo);
        Storage::disk('public')->assertExists($originalPhoto);
    }

    public function test_uploading_a_replacement_photo_deletes_the_old_one(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Replace Me', 'slug' => 'replace-me', 'status' => 'active',
            'photo' => UploadedFile::fake()->image('first.jpg', 500, 500),
        ]);
        $artist = Artist::where('slug', 'replace-me')->firstOrFail();
        $originalPhoto = $artist->photo;

        $this->put("/admin/artists/{$artist->id}", [
            'name' => 'Replace Me',
            'slug' => 'replace-me',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('second.jpg', 500, 500),
        ])->assertRedirect();

        $artist->refresh();
        $this->assertNotSame($originalPhoto, $artist->photo);
        Storage::disk('public')->assertMissing($originalPhoto);
        Storage::disk('public')->assertExists($artist->photo);
    }

    public function test_checking_remove_photo_clears_it_and_deletes_the_file(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Remove Me', 'slug' => 'remove-me', 'status' => 'active',
            'photo' => UploadedFile::fake()->image('photo.jpg', 500, 500),
        ]);
        $artist = Artist::where('slug', 'remove-me')->firstOrFail();
        $originalPhoto = $artist->photo;

        $this->put("/admin/artists/{$artist->id}", [
            'name' => 'Remove Me',
            'slug' => 'remove-me',
            'status' => 'active',
            'remove_photo' => '1',
        ])->assertRedirect();

        $artist->refresh();
        $this->assertNull($artist->photo);
        Storage::disk('public')->assertMissing($originalPhoto);
    }

    public function test_deleting_an_artist_removes_its_stored_files(): void
    {
        $this->post('/admin/artists', [
            'name' => 'Delete Me', 'slug' => 'delete-me', 'status' => 'active',
            'photo' => UploadedFile::fake()->image('photo.jpg', 500, 500),
        ]);
        $artist = Artist::where('slug', 'delete-me')->firstOrFail();
        $photoPath = $artist->photo;

        $this->delete("/admin/artists/{$artist->id}")->assertRedirect(route('admin.artists.index'));

        $this->assertDatabaseMissing('artists', ['id' => $artist->id]);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin')
            ->get('/admin/artists')
            ->assertForbidden();
    }
}
