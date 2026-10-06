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
