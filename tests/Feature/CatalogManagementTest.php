<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs(
            $this->createAdmin([
                'view-categories', 'create-categories', 'edit-categories', 'delete-categories',
                'view-tags', 'create-tags', 'edit-tags', 'delete-tags',
                'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
                'view-media-content', 'create-media-content', 'edit-media-content', 'delete-media-content',
            ]),
            'admin'
        );
    }

    /**
     * Categories, and the top-level tag tree, come pre-seeded by their own
     * migrations (the "By Categories" / "By Tag" lists from the scope doc),
     * so every fixture below uses a `qa-` slug to avoid colliding with them.
     */
    public function test_it_creates_a_category(): void
    {
        $this->post('/admin/categories', [
            'name' => 'QA Category',
            'slug' => 'qa-category',
            'sort_order' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['slug' => 'qa-category']);
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);

        $this->put("/admin/categories/{$category->id}", [
            'name' => 'QA Category Renamed',
            'slug' => 'qa-category',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertSame('QA Category Renamed', $category->fresh()->name);
    }

    public function test_deleting_a_category_cascades_to_its_programs(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $program = Program::create(['category_id' => $category->id, 'name' => 'QA Program', 'slug' => 'qa-program']);

        $this->delete("/admin/categories/{$category->id}")->assertRedirect();

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_it_creates_a_top_level_tag_with_no_parent(): void
    {
        $this->post('/admin/tags', [
            'parent_id' => '',
            'name' => 'QA Root',
            'slug' => 'qa-root',
        ])->assertRedirect(route('admin.tags.index'));

        $this->assertDatabaseHas('tags', ['slug' => 'qa-root', 'parent_id' => null]);
    }

    public function test_it_creates_a_nested_tag(): void
    {
        $parent = Tag::create(['name' => 'QA Root', 'slug' => 'qa-root']);

        $this->post('/admin/tags', [
            'parent_id' => $parent->id,
            'name' => 'QA Child',
            'slug' => 'qa-child',
        ])->assertRedirect(route('admin.tags.index'));

        $this->assertDatabaseHas('tags', ['slug' => 'qa-child', 'parent_id' => $parent->id]);
    }

    public function test_sibling_tags_under_different_parents_may_share_a_slug(): void
    {
        $branchA = Tag::create(['name' => 'QA Branch A', 'slug' => 'qa-branch-a']);
        $branchB = Tag::create(['name' => 'QA Branch B', 'slug' => 'qa-branch-b']);

        $this->post('/admin/tags', ['parent_id' => $branchA->id, 'name' => 'Genre', 'slug' => 'qa-genre'])
            ->assertRedirect(route('admin.tags.index'));
        $this->post('/admin/tags', ['parent_id' => $branchB->id, 'name' => 'Genre', 'slug' => 'qa-genre'])
            ->assertRedirect(route('admin.tags.index'));

        $this->assertDatabaseHas('tags', ['slug' => 'qa-genre', 'parent_id' => $branchA->id]);
        $this->assertDatabaseHas('tags', ['slug' => 'qa-genre', 'parent_id' => $branchB->id]);
    }

    public function test_a_tag_cannot_become_a_child_of_its_own_descendant(): void
    {
        $parent = Tag::create(['name' => 'QA Parent', 'slug' => 'qa-parent']);
        $child = Tag::create(['parent_id' => $parent->id, 'name' => 'QA Child', 'slug' => 'qa-child']);

        $this->put("/admin/tags/{$parent->id}", [
            'parent_id' => $child->id,
            'name' => 'QA Parent',
            'slug' => 'qa-parent',
        ])->assertStatus(422);
    }

    public function test_deleting_a_tag_cascades_to_its_children(): void
    {
        $parent = Tag::create(['name' => 'QA Parent', 'slug' => 'qa-parent']);
        $child = Tag::create(['parent_id' => $parent->id, 'name' => 'QA Child', 'slug' => 'qa-child']);

        $this->delete("/admin/tags/{$parent->id}")->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $child->id]);
    }

    public function test_it_creates_a_program(): void
    {
        $type = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();

        $this->post('/admin/programs', [
            'tag_id' => $type->id,
            'name' => 'QA Program',
            'slug' => 'qa-program',
            'is_published' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('programs', ['slug' => 'qa-program', 'is_published' => true]);
    }

    public function test_uploading_a_program_cover_image_is_cropped_to_1200x400_and_converted_to_webp(): void
    {
        $type = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();

        $this->post('/admin/programs', [
            'tag_id' => $type->id,
            'name' => 'QA Program',
            'slug' => 'qa-program',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 2000, 500),
        ])->assertRedirect();

        $program = Program::where('slug', 'qa-program')->firstOrFail();
        $this->assertStringEndsWith('.webp', $program->cover_image);

        $image = imagecreatefromstring(Storage::disk('public')->get($program->cover_image));
        $this->assertSame([1200, 400], [imagesx($image), imagesy($image)]);
        $this->assertStringContainsString('/storage/', $program->cover_image_url);
    }

    public function test_checking_remove_cover_image_clears_it_and_deletes_the_file(): void
    {
        $type = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();
        $this->post('/admin/programs', [
            'tag_id' => $type->id,
            'name' => 'QA Program',
            'slug' => 'qa-program',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 2000, 500),
        ]);
        $program = Program::where('slug', 'qa-program')->firstOrFail();
        $originalCoverImage = $program->cover_image;

        $this->put("/admin/programs/{$program->id}", [
            'tag_id' => $type->id,
            'name' => 'QA Program',
            'slug' => 'qa-program',
            'remove_cover_image' => '1',
        ])->assertRedirect();

        $program->refresh();
        $this->assertNull($program->cover_image);
        Storage::disk('public')->assertMissing($originalCoverImage);
    }

    public function test_it_creates_media_content_with_no_program_or_duration(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $tag = Tag::create(['name' => 'QA Tag', 'slug' => 'qa-tag']);

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'program_id' => '',
            'title' => 'QA Track',
            'slug' => 'qa-track',
            'media_type' => 'audio',
            'duration_seconds' => '',
            'status' => 'public',
            'tags' => [$tag->id],
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertNull($mediaContent->program_id);
        $this->assertNull($mediaContent->duration_seconds);
        $this->assertTrue($mediaContent->tags->contains($tag));
        $this->assertNotNull($mediaContent->published_at);
    }

    public function test_it_updates_media_content_tags(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $keep = Tag::create(['name' => 'QA Keep', 'slug' => 'qa-keep']);
        $drop = Tag::create(['name' => 'QA Drop', 'slug' => 'qa-drop']);
        $mediaContent = MediaContent::create([
            'category_id' => $category->id, 'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
        ]);
        $mediaContent->tags()->sync([$keep->id, $drop->id]);

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $category->id,
            'title' => 'QA Track',
            'slug' => 'qa-track',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$keep->id],
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent->refresh();
        $this->assertTrue($mediaContent->tags->contains($keep));
        $this->assertFalse($mediaContent->tags->contains($drop));
    }

    public function test_creating_media_content_automatically_sets_the_author_to_the_current_admin(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $artist = Artist::create(['name' => 'QA Artist', 'slug' => 'qa-artist']);

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'private',
            'artist_id' => $artist->id,
        ])->assertRedirect(route('admin.media-contents.index'));

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertTrue($mediaContent->artist->is($artist));
        $this->assertTrue($mediaContent->author->is(auth('admin')->user()));
    }

    public function test_updating_media_content_does_not_change_its_author(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $originalAuthor = auth('admin')->user();

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'private',
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();

        // A different admin edits it later — the original author should stick.
        $this->actingAs($this->createAdmin(['edit-media-content']), 'admin');
        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $category->id,
            'title' => 'QA Track Updated', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect();

        $this->assertTrue($mediaContent->fresh()->author->is($originalAuthor));
    }

    public function test_the_media_content_create_and_edit_pages_render(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $mediaContent = MediaContent::create([
            'category_id' => $category->id, 'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'description' => '<p>Some <strong>rich</strong> text.</p>',
        ]);

        $this->get('/admin/media-contents/create')->assertOk()->assertSee('New media content');
        // The textarea holds escaped HTML (the browser decodes it back to source markup for Quill to load).
        $this->get("/admin/media-contents/{$mediaContent->id}/edit")->assertOk()->assertSee('Some &lt;strong&gt;rich&lt;/strong&gt; text.', false);
    }

    public function test_the_tag_picker_shows_each_tags_media_content_count(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $popular = Tag::create(['name' => 'QA Popular', 'slug' => 'qa-popular']);
        $quiet = Tag::create(['name' => 'QA Quiet', 'slug' => 'qa-quiet']);

        foreach (['a', 'b'] as $suffix) {
            $mediaContent = MediaContent::create([
                'category_id' => $category->id, 'title' => "QA Track {$suffix}", 'slug' => "qa-track-{$suffix}", 'media_type' => 'audio',
            ]);
            $mediaContent->tags()->attach($popular->id);
        }

        $tree = Tag::tree();
        $this->assertSame(2, $tree->firstWhere('id', $popular->id)->count);
        $this->assertSame(0, $tree->firstWhere('id', $quiet->id)->count);

        $response = $this->get('/admin/media-contents/create')->assertOk();
        $this->assertMatchesRegularExpression('/QA Popular<\/span>\s*<small>2<\/small>/', $response->getContent());
        $this->assertMatchesRegularExpression('/QA Quiet<\/span>\s*<small>0<\/small>/', $response->getContent());
    }

    public function test_admin_without_the_permission_is_forbidden(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin')
            ->get('/admin/categories')
            ->assertForbidden();
    }
}
