<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** A thumbnail or cover that can't be used is an error on its field, not an error page — and never costs the old image. */
class UnreadableImageTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs($this->createAdmin([
            'view-media-content', 'create-media-content', 'edit-media-content',
            'view-artists', 'create-artists', 'edit-artists',
        ]), 'admin');
    }

    private function payload(array $extra = []): array
    {
        return [
            'category_id' => Category::where('slug', 'song')->value('id'),
            'title' => 'My Song',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            ...$extra,
        ];
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_a_thumbnail_url_that_is_a_web_page_is_a_field_error_and_the_form_is_kept(): void
    {
        Http::fake(['*' => Http::response('<html><body>This is a web page, not a picture.</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->from('/admin/media-contents/create/song')
            ->post('/admin/media-contents', $this->payload(['from' => 'music', 'thumbnail_url' => 'https://example.com/watch?v=abc']))
            ->assertRedirect('/admin/media-contents/create/song')
            ->assertSessionHasErrors(['thumbnail_url' => "That address did not return a picture we can read. Paste the picture's own address (ending in .jpg, .png, .webp), not a web page."])
            ->assertSessionHasInput('title', 'My Song');

        $this->assertSame(0, MediaContent::count());
    }

    public function test_a_thumbnail_url_that_cannot_be_downloaded_is_a_field_error(): void
    {
        // One fake per address (an earlier fake for '*' would keep answering).
        Http::fake([
            'example.com/missing.png' => Http::response('nope', 404),
            'slow.example.com/*' => fn () => throw new ConnectionException('cURL error 28: timed out'),
        ]);
        $this->post('/admin/media-contents', $this->payload(['thumbnail_url' => 'https://example.com/missing.png']))
            ->assertSessionHasErrors(['thumbnail_url' => 'Could not download that image URL.']);

        // A timeout or an unreachable host used to be a server error.
        $this->post('/admin/media-contents', $this->payload(['thumbnail_url' => 'https://slow.example.com/a.png']))
            ->assertSessionHasErrors(['thumbnail_url' => 'Could not download that image URL.']);

        $this->assertSame(0, MediaContent::count());
    }

    public function test_an_uploaded_thumbnail_that_looks_like_an_image_but_cannot_be_decoded_is_a_field_error(): void
    {
        // A real PNG cut off half way: it passes the "is an image" check by its header but can't be decoded.
        $png = $this->pngBytes();
        $broken = UploadedFile::fake()->createWithContent('thumb.png', substr($png, 0, (int) (strlen($png) / 2)));

        $this->post('/admin/media-contents', $this->payload(['thumbnail' => $broken]))
            ->assertSessionHasErrors('thumbnail')
            ->assertSessionHasInput('title', 'My Song');
        $this->assertStringContainsString('could not be read as a picture', session('errors')->first('thumbnail'));
        $this->assertSame(0, MediaContent::count());
    }

    public function test_a_bad_replacement_keeps_the_old_thumbnail_on_disk_and_on_the_record(): void
    {
        $this->post('/admin/media-contents', $this->payload(['thumbnail' => UploadedFile::fake()->image('thumb.jpg', 800, 600)]))->assertRedirect();
        $item = MediaContent::firstOrFail();
        $old = $item->thumbnail;
        Storage::disk('public')->assertExists($old);

        Http::fake([
            'example.com/page' => Http::response('<html>not a picture</html>', 200),
            'example.com/new.png' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->put("/admin/media-contents/{$item->id}", $this->payload(['slug' => $item->slug, 'thumbnail_url' => 'https://example.com/page']))
            ->assertSessionHasErrors('thumbnail_url');

        // Before: the old file was deleted first, so the record pointed at a file that was gone.
        $this->assertSame($old, $item->fresh()->thumbnail);
        Storage::disk('public')->assertExists($old);

        // A good replacement still swaps them.
        $this->put("/admin/media-contents/{$item->id}", $this->payload(['slug' => $item->slug, 'thumbnail_url' => 'https://example.com/new.png']))
            ->assertSessionDoesntHaveErrors(['thumbnail_url']);
        $new = $item->fresh()->thumbnail;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_the_same_handling_covers_the_other_image_fields(): void
    {
        $png = $this->pngBytes();
        Http::fake([
            'example.com/page' => Http::response('<html>not a picture</html>', 200),
            'example.com/photo.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        // An artist's photo (its own field name), by URL and by upload.
        $artist = ['name' => 'Some Artist', 'slug' => 'some-artist', 'status' => 'active'];
        $this->post('/admin/artists', [...$artist, 'photo_url' => 'https://example.com/page'])
            ->assertSessionHasErrors(['photo_url' => "That address did not return a picture we can read. Paste the picture's own address (ending in .jpg, .png, .webp), not a web page."]);
        $this->post('/admin/artists', [...$artist, 'photo' => UploadedFile::fake()->createWithContent('p.png', substr($png, 0, (int) (strlen($png) / 2)))])
            ->assertSessionHasErrors('photo');
        $this->assertSame(0, Artist::count());

        // And with a readable picture the artist is saved, so it was the picture that stopped the two above.
        $this->post('/admin/artists', [...$artist, 'photo_url' => 'https://example.com/photo.png'])->assertSessionDoesntHaveErrors(['photo_url']);
        $this->assertSame(1, Artist::count());
    }
}
