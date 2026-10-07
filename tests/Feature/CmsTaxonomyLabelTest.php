<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** The sidebar group and the heading over each page of Podcasts, Article, Categories, Tags and Artists say "Taxonomy". */
class CmsTaxonomyLabelTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const PERMISSIONS = [
        'view-programs', 'create-programs', 'edit-programs',
        'view-media-content', 'view-categories', 'create-categories', 'edit-categories',
        'view-tags', 'create-tags', 'edit-tags',
        'view-artists', 'create-artists', 'edit-artists',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(self::PERMISSIONS), 'admin');
    }

    private function eyebrow(string $html): ?string
    {
        return preg_match('/<p class="eyebrow">([^<]*)<\/p>/', $html, $m) ? $m[1] : null;
    }

    public function test_the_sidebar_group_is_taxonomy(): void
    {
        $html = $this->get('/admin/podcasts')->assertOk()->getContent();
        $nav = substr($html, $start = strpos($html, 'main-nav'), strpos($html, '</nav>', $start) - $start);

        // One heading, over Podcasts, Article, Categories, Tags and Artists.
        $this->assertSame(1, substr_count($nav, 'class="nav-section"'));
        $this->assertMatchesRegularExpression('/<p class="nav-section">Taxonomy<\/p>\s*<a href="[^"]*\/admin\/podcasts"/', $nav);
        $this->assertStringNotContainsString('Catalog', $nav);
    }

    public function test_every_page_under_it_has_taxonomy_as_its_heading_not_catalog(): void
    {
        $category = Category::where('slug', 'song')->firstOrFail();
        $tag = Tag::firstOrFail();
        $artist = Artist::create(['name' => 'Some Artist', 'slug' => 'some-artist', 'status' => 'active']);
        $program = Program::create(['category_id' => Category::where('slug', 'podcast')->value('id'), 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);

        foreach ([
            '/admin/music', '/admin/music?category=podcast',
            '/admin/media-contents',
            '/admin/podcasts', '/admin/podcasts/create',
            '/admin/categories', '/admin/categories/create', "/admin/categories/{$category->id}/edit",
            '/admin/tags', '/admin/tags/create', "/admin/tags/{$tag->id}/edit",
            '/admin/artists', '/admin/artists/create', "/admin/artists/{$artist->id}/edit",
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame('Taxonomy', $this->eyebrow($html), $url);
            $this->assertStringNotContainsString('Catalog', substr($html, strpos($html, '<body')), $url);
        }
    }

    public function test_the_podcast_edit_page_has_no_page_heading_but_is_still_under_taxonomy(): void
    {
        $program = Program::create(['category_id' => Category::where('slug', 'podcast')->value('id'), 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);

        $html = $this->get("/admin/podcasts/{$program->id}/edit")->assertOk()->getContent();

        // The bar at the top of the form is its heading: no "Taxonomy / Edit Podcast / Update …" block above it.
        $this->assertStringNotContainsString('<h1>Edit Podcast</h1>', $html);
        $this->assertStringNotContainsString('Update Morning Brief.', $html);
        $this->assertSame('Episodes', $this->eyebrow($html), 'the only heading left is the Episodes one below the form');
        $this->assertStringContainsString('<strong class="editor-topbar-title">Morning Brief</strong>', $html);
        // The browser tab and the sidebar still say where it is.
        $this->assertStringContainsString('<title>Edit Podcast', $html);
        $this->assertStringContainsString('<p class="nav-section">Taxonomy</p>', $html);
        $this->assertStringNotContainsString('Catalog', substr($html, strpos($html, '<body')));

        // The New Podcast page keeps its heading (and its "create the show first" hint).
        $new = $this->get('/admin/podcasts/create')->assertOk()->getContent();
        $this->assertSame('Taxonomy', $this->eyebrow($new));
        $this->assertStringContainsString('<h1>New Podcast</h1>', $new);
    }

    public function test_no_view_still_uses_the_catalog_label(): void
    {
        $views = glob(base_path('src/*/Views/{,*/}*.blade.php'), GLOB_BRACE);
        $this->assertGreaterThan(50, count($views), 'the search found the views it is meant to check');
        $this->assertTrue(
            collect($views)->contains(fn (string $file) => str_ends_with(str_replace('\\', '/', $file), 'CmsAdmin/Views/partials/sidebar.blade.php')),
            'the sidebar is among the views checked',
        );

        foreach ($views as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString("__('Catalog')", $source, $file);
            $this->assertStringNotContainsString("'section' => 'Catalog'", $source, $file);
        }
    }

    public function test_taxonomy_is_translated_into_khmer(): void
    {
        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect();

        $html = $this->get('/admin/podcasts')->assertOk()->getContent();

        $this->assertSame('ចំណាត់ថ្នាក់', $this->eyebrow($html));
        $this->assertStringContainsString('<p class="nav-section">ចំណាត់ថ្នាក់</p>', $html);
        $this->assertStringNotContainsString('កាតាឡុក', $html);
    }
}
