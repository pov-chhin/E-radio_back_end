<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\Tag;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CmsTagCategoryTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['view-tags', 'create-tags', 'edit-tags', 'delete-tags']), 'admin');
    }

    private function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    public function test_the_seeded_trees_sit_under_the_category_of_the_same_name(): void
    {
        foreach (['podcast', 'health', 'economyfinance', 'news', 'song', 'ambient-music'] as $slug) {
            $root = Tag::where('slug', $slug)->whereNull('parent_id')->firstOrFail();
            $this->assertSame($this->category($slug)->id, $root->category_id, "root {$slug}");

            // ...and so does everything nested under it, however deep.
            foreach ($root->descendantIds() as $id) {
                $this->assertSame($root->category_id, Tag::find($id)->category_id, "tag {$id} under {$slug}");
            }
        }

        $this->assertSame(0, Tag::whereNull('category_id')->count());
    }

    public function test_a_top_level_tag_takes_the_category_it_is_given(): void
    {
        $this->post('/admin/tags', ['name' => 'QA Tag', 'slug' => 'qa-tag', 'category_id' => $this->category('news')->id])
            ->assertRedirect(route('admin.tags.index'));

        $this->assertSame($this->category('news')->id, Tag::where('slug', 'qa-tag')->firstOrFail()->category_id);
    }

    public function test_a_sub_tag_follows_its_parents_category_whatever_is_posted(): void
    {
        $parent = Tag::where('slug', 'song')->whereNull('parent_id')->firstOrFail();

        $this->post('/admin/tags', [
            'parent_id' => $parent->id,
            'name' => 'QA Sub',
            'slug' => 'qa-sub',
            'category_id' => $this->category('news')->id,
        ])->assertRedirect();

        $this->assertSame($this->category('song')->id, Tag::where('slug', 'qa-sub')->firstOrFail()->category_id);
    }

    public function test_changing_a_top_level_tags_category_hands_it_down_to_the_tags_beneath(): void
    {
        $root = Tag::create(['name' => 'QA Root', 'slug' => 'qa-root', 'category_id' => $this->category('news')->id]);
        $child = Tag::create(['name' => 'QA Child', 'slug' => 'qa-child', 'parent_id' => $root->id, 'category_id' => $root->category_id]);
        $grandchild = Tag::create(['name' => 'QA Grandchild', 'slug' => 'qa-grandchild', 'parent_id' => $child->id, 'category_id' => $root->category_id]);

        $this->put("/admin/tags/{$root->id}", [
            'name' => 'QA Root', 'slug' => 'qa-root', 'category_id' => $this->category('health')->id,
        ])->assertRedirect();

        $health = $this->category('health')->id;
        $this->assertSame($health, $child->fresh()->category_id);
        $this->assertSame($health, $grandchild->fresh()->category_id);
    }

    public function test_moving_a_tag_under_a_parent_gives_it_the_parents_category(): void
    {
        $tag = Tag::create(['name' => 'QA Loose', 'slug' => 'qa-loose', 'category_id' => $this->category('news')->id]);
        $parent = Tag::where('slug', 'song')->whereNull('parent_id')->firstOrFail();

        $this->put("/admin/tags/{$tag->id}", [
            'parent_id' => $parent->id, 'name' => 'QA Loose', 'slug' => 'qa-loose',
        ])->assertRedirect();

        $this->assertSame($this->category('song')->id, $tag->fresh()->category_id);
    }

    public function test_the_tag_form_offers_the_categories_and_the_list_shows_them(): void
    {
        $html = $this->get('/admin/tags/create')->assertOk()->getContent();
        foreach (Category::all() as $category) {
            $this->assertMatchesRegularExpression('/<option value="'.$category->id.'"[^>]*>'.preg_quote(e($category->name), '/').'</', $html);
        }
        // Parent options carry their category so the form can show what a sub tag will inherit.
        $song = Tag::where('slug', 'song')->whereNull('parent_id')->firstOrFail();
        $this->assertStringContainsString('<option value="'.$song->id.'" data-category-id="'.$song->category_id.'"', $html);

        $this->get('/admin/tags')->assertOk()->assertSee('Category')->assertSee('Ambient Music');
    }

    public function test_deleting_a_category_leaves_its_tags_without_one(): void
    {
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $tag = Tag::create(['name' => 'QA Tag', 'slug' => 'qa-tag', 'category_id' => $category->id]);

        $category->delete();

        $this->assertNull($tag->fresh()->category_id);
    }
}
