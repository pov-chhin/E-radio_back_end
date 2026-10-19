<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\Tag;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** A tag can carry a picture — the card the app shows for an Ambient Music mood — uploaded in the admin and served by /api/tags. */
class CmsTagImageTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs($this->createAdmin(['view-tags', 'create-tags', 'edit-tags', 'delete-tags']), 'admin');
    }

    private function dimensionsOf(string $path): array
    {
        $image = imagecreatefromstring(Storage::disk('public')->get($path));

        return [imagesx($image), imagesy($image)];
    }

    /** The node for $slug in the /api/tags tree, however deep. */
    private function apiNode(string $slug, ?array $nodes = null): ?array
    {
        $nodes ??= $this->getJson('/api/tags')->assertOk()->json('data');

        foreach ($nodes as $node) {
            if ($node['slug'] === $slug) {
                return $node;
            }
            if ($found = $this->apiNode($slug, $node['children'])) {
                return $found;
            }
        }

        return null;
    }

    public function test_a_tag_picture_is_cropped_to_616x296_and_converted_to_webp(): void
    {
        $this->post('/admin/tags', [
            'name' => 'QA Mood', 'slug' => 'qa-mood',
            'image' => UploadedFile::fake()->image('card.png', 1000, 600),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.tags.index'));

        $tag = Tag::where('slug', 'qa-mood')->firstOrFail();
        $this->assertStringEndsWith('.webp', $tag->image);
        $this->assertSame([616, 296], $this->dimensionsOf($tag->image));
        $this->assertNull($tag->image_disk);
        Storage::disk('public')->assertExists($tag->image);
        $this->assertStringContainsString('/storage/'.$tag->image, $tag->image_url);
    }

    public function test_the_api_serves_the_picture_url_and_null_when_there_is_none(): void
    {
        $with = Tag::create(['name' => 'With', 'slug' => 'with-picture']);
        Tag::create(['name' => 'Without', 'slug' => 'without-picture']);

        $this->put("/admin/tags/{$with->id}", [
            'name' => 'With', 'slug' => 'with-picture',
            'image' => UploadedFile::fake()->image('card.jpg', 800, 400),
        ])->assertSessionHasNoErrors();

        $this->assertSame($with->fresh()->image_url, $this->apiNode('with-picture')['image_url']);
        $this->assertNull($this->apiNode('without-picture')['image_url']);
    }

    public function test_a_child_tags_picture_is_in_the_api_tree(): void
    {
        $parent = Tag::create(['name' => 'Parent', 'slug' => 'qa-parent']);

        $this->post('/admin/tags', [
            'parent_id' => $parent->id, 'name' => 'Kid', 'slug' => 'qa-kid',
            'image' => UploadedFile::fake()->image('kid.png', 616, 296),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($this->apiNode('qa-kid')['image_url']);
        $this->assertNull($this->apiNode('qa-parent')['image_url']);
    }

    public function test_replacing_the_picture_removes_the_old_file(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-replace']);
        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-replace', 'image' => UploadedFile::fake()->image('a.png', 700, 400)]);
        $old = $tag->fresh()->image;

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-replace', 'image' => UploadedFile::fake()->image('b.png', 700, 400)])
            ->assertSessionHasNoErrors();

        $new = $tag->fresh()->image;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_saving_without_a_new_picture_keeps_the_current_one(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-keep']);
        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-keep', 'image' => UploadedFile::fake()->image('a.png', 700, 400)]);
        $path = $tag->fresh()->image;

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Renamed', 'slug' => 'qa-keep'])->assertSessionHasNoErrors();

        $this->assertSame($path, $tag->fresh()->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_remove_picture_clears_it_and_deletes_the_file(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-remove']);
        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-remove', 'image' => UploadedFile::fake()->image('a.png', 700, 400)]);
        $path = $tag->fresh()->image;

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-remove', 'remove_image' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($tag->fresh()->image);
        $this->assertNull($tag->fresh()->image_disk);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->apiNode('qa-remove')['image_url']);
    }

    public function test_deleting_a_tag_deletes_its_picture_and_those_of_the_tags_beneath_it(): void
    {
        $parent = Tag::create(['name' => 'Parent', 'slug' => 'qa-doomed']);
        $this->put("/admin/tags/{$parent->id}", ['name' => 'Parent', 'slug' => 'qa-doomed', 'image' => UploadedFile::fake()->image('p.png', 700, 400)]);
        $this->post('/admin/tags', ['parent_id' => $parent->id, 'name' => 'Kid', 'slug' => 'doomed-kid', 'image' => UploadedFile::fake()->image('k.png', 700, 400)]);
        $paths = [$parent->fresh()->image, Tag::where('slug', 'doomed-kid')->firstOrFail()->image];

        $this->delete("/admin/tags/{$parent->id}")->assertRedirect();

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->post('/admin/tags', [
            'name' => 'Bad', 'slug' => 'qa-bad', 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('tags', ['slug' => 'qa-bad']);
    }

    /** The five moods the original card pictures belong to, by tag slug. */
    private const CARD_MOODS = ['working', 'lobbylounge', 'studying', 'yoga', 'Sports'];

    private function mood(string $slug): Tag
    {
        $ambient = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail();

        return Tag::where('parent_id', $ambient->id)->where('slug', $slug)->firstOrFail();
    }

    /** The migrations seed no Sports mood; the live one (added in the admin) is slugged `Sports`. */
    private function addLiveMoods(): void
    {
        $ambient = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail();

        Tag::firstOrCreate(
            ['parent_id' => $ambient->id, 'slug' => 'Sports'],
            ['category_id' => $ambient->category_id, 'name' => 'Sports'],
        );
    }

    public function test_the_import_command_stores_the_original_card_pictures_on_their_moods(): void
    {
        $this->addLiveMoods();
        $this->artisan('tags:import-ambient-cards')->assertSuccessful();

        foreach (self::CARD_MOODS as $slug) {
            $tag = $this->mood($slug);
            $this->assertStringEndsWith('.webp', $tag->image, $slug);
            $this->assertSame([616, 296], $this->dimensionsOf($tag->image), $slug);
            $this->assertSame($tag->image_url, $this->apiNode($slug)['image_url'], $slug);
        }

        // Reading and Khmer Traditional Music never had a card picture.
        $this->assertNull($this->mood('reading')->image);
        $this->assertNull($this->mood('khmer-traditional-music')->image);
    }

    public function test_the_import_command_also_stores_every_banner_illustration_keeping_its_transparency(): void
    {
        $this->addLiveMoods();

        $this->artisan('tags:import-ambient-cards')->assertSuccessful();

        foreach ([...self::CARD_MOODS, 'reading', 'khmer-traditional-music'] as $slug) {
            $tag = $this->mood($slug);
            $this->assertStringEndsWith('.webp', $tag->banner_image, $slug);
            $this->assertSame($tag->banner_image_url, $this->apiNode($slug)['banner_image_url'], $slug);

            // Scaled down to fit, never cropped or enlarged: nothing past 480, the corner still see-through.
            [$width, $height] = $this->dimensionsOf($tag->banner_image);
            $this->assertLessThanOrEqual(480, max($width, $height), $slug);
            $image = imagecreatefromstring(Storage::disk('public')->get($tag->banner_image));
            $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F, "{$slug} lost its transparency");
        }
    }

    public function test_the_import_command_leaves_a_picture_the_admin_uploaded_unless_forced(): void
    {
        $this->addLiveMoods();
        $working = $this->mood('working');
        $this->put("/admin/tags/{$working->id}", [
            'name' => $working->name, 'slug' => 'working', 'parent_id' => $working->parent_id,
            'image' => UploadedFile::fake()->image('mine.png', 700, 400),
        ])->assertSessionHasNoErrors();
        $mine = $working->fresh()->image;

        $this->artisan('tags:import-ambient-cards')->assertSuccessful();

        $this->assertSame($mine, $working->fresh()->image);
        Storage::disk('public')->assertExists($mine);
        $this->assertNotNull($this->mood('yoga')->image);

        $this->artisan('tags:import-ambient-cards --force')->assertSuccessful();

        $this->assertNotSame($mine, $working->fresh()->image);
        Storage::disk('public')->assertMissing($mine);
        Storage::disk('public')->assertExists($working->fresh()->image);
    }

    public function test_a_banner_picture_is_scaled_to_fit_not_cropped_and_served_by_the_api(): void
    {
        $this->post('/admin/tags', [
            'name' => 'QA Banner', 'slug' => 'qa-banner',
            'banner_image' => UploadedFile::fake()->image('hero.png', 300, 600),
        ])->assertSessionHasNoErrors();

        $tag = Tag::where('slug', 'qa-banner')->firstOrFail();
        $this->assertStringEndsWith('.webp', $tag->banner_image);
        // 300×600 fits 480×480 at 0.8 — the shape is kept.
        $this->assertSame([240, 480], $this->dimensionsOf($tag->banner_image));
        $this->assertNull($tag->banner_image_disk);
        $this->assertNull($tag->image);
        $this->assertSame($tag->banner_image_url, $this->apiNode('qa-banner')['banner_image_url']);
        $this->assertNull($this->apiNode('qa-banner')['image_url']);
    }

    public function test_a_small_banner_picture_is_not_enlarged(): void
    {
        $this->post('/admin/tags', [
            'name' => 'QA Small', 'slug' => 'qa-small',
            'banner_image' => UploadedFile::fake()->image('hero.png', 200, 100),
        ])->assertSessionHasNoErrors();

        $this->assertSame([200, 100], $this->dimensionsOf(Tag::where('slug', 'qa-small')->firstOrFail()->banner_image));
    }

    public function test_replacing_and_removing_the_banner_picture_cleans_up_the_files(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-banner-life']);
        $card = null;

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-banner-life',
            'image' => UploadedFile::fake()->image('c.png', 700, 400), 'banner_image' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $card = $tag->fresh()->image;
        $old = $tag->fresh()->banner_image;

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-banner-life', 'banner_image' => UploadedFile::fake()->image('b.png', 300, 300)])
            ->assertSessionHasNoErrors();
        $new = $tag->fresh()->banner_image;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
        // The card picture was left alone.
        $this->assertSame($card, $tag->fresh()->image);

        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-banner-life', 'remove_banner_image' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($tag->fresh()->banner_image);
        $this->assertNull($tag->fresh()->banner_image_disk);
        Storage::disk('public')->assertMissing($new);
        $this->assertSame($card, $tag->fresh()->image);
        Storage::disk('public')->assertExists($card);
    }

    public function test_deleting_a_tag_also_deletes_its_banner_picture(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-banner-doomed']);
        $this->put("/admin/tags/{$tag->id}", ['name' => 'Mood', 'slug' => 'qa-banner-doomed', 'banner_image' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $path = $tag->fresh()->banner_image;

        $this->delete("/admin/tags/{$tag->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_form_can_upload_a_file(): void
    {
        $tag = Tag::create(['name' => 'Mood', 'slug' => 'qa-form']);

        foreach (['/admin/tags/create', "/admin/tags/{$tag->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('enctype="multipart/form-data"', $form, $url);
            $this->assertMatchesRegularExpression('/<input id="image" name="image" type="file"/', $form, $url);
            $this->assertStringContainsString('name="image_url"', $form, $url);
            $this->assertMatchesRegularExpression('/<input id="banner_image" name="banner_image" type="file"/', $form, $url);
            $this->assertStringContainsString('name="banner_image_url"', $form, $url);
        }
    }
}
