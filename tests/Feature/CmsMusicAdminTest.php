<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CmsMusicAdminTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function signIn(array $permissions = [
        'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
        'view-media-content', 'create-media-content', 'edit-media-content', 'delete-media-content',
    ]): void
    {
        $this->actingAs($this->createAdmin($permissions), 'admin');
    }

    private function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    private function track(string $title, string $categorySlug, array $attributes = []): MediaContent
    {
        return MediaContent::create([
            'category_id' => $this->category($categorySlug)->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            ...$attributes,
        ]);
    }

    public function test_music_sits_right_below_programs_in_the_sidebar(): void
    {
        $this->signIn();

        $html = $this->get('/admin/programs')->assertOk()->getContent();

        $programs = strpos($html, 'href="'.route('admin.programs.index').'"');
        $music = strpos($html, 'href="'.route('admin.music.index').'"');

        $this->assertNotFalse($music);
        $this->assertGreaterThan($programs, $music);
        // Categories needs its own permission, so it isn't in this admin's menu.
        $this->assertFalse(strpos($html, 'href="'.route('admin.categories.index').'"'));
    }

    public function test_the_page_and_its_menu_item_are_called_article(): void
    {
        $this->signIn();

        $html = $this->get('/admin/music')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.music.index'), '/').'"[^>]*>\s*<span class="nav-icon[^"]*"><\/span><span>Article<\/span>/', $html);
        $this->assertStringContainsString('<h1>Article</h1>', $html);
        $this->assertStringContainsString('<title>Article', $html);
        $this->assertStringNotContainsString('<h1>Music</h1>', $html);
        $this->assertStringNotContainsString('<span>Music</span>', $html);

        $form = $this->get('/admin/media-contents/create?category=song&from=music')->assertOk();
        $form->assertSee('aria-label="Back to articles"', false);
    }

    public function test_the_media_content_menu_is_hidden_but_its_pages_still_work(): void
    {
        $this->signIn();

        $html = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('admin.media-contents.index').'"', $html);
        $this->assertStringNotContainsString('Media Content', substr($html, strpos($html, 'main-nav'), strpos($html, '</nav>') - strpos($html, 'main-nav')));

        // Still there by its own address.
        $this->get('/admin/media-contents')->assertOk();
        $this->get('/admin/media-contents/create')->assertOk();
    }

    public function test_the_sidebar_lights_music_on_media_pages_and_programs_on_a_programs_episodes(): void
    {
        $this->signIn();
        $lit = fn (string $html, string $route) => (bool) preg_match('/<a href="'.preg_quote(route($route), '/').'" class="active">/', $html);

        $media = $this->get('/admin/media-contents/create')->assertOk()->getContent();
        $this->assertTrue($lit($media, 'admin.music.index'));
        $this->assertFalse($lit($media, 'admin.programs.index'));

        $program = \Src\Catalog\Models\Program::create([
            'category_id' => $this->category('podcast')->id, 'name' => 'Lit Show', 'slug' => 'lit-show', 'is_published' => true,
        ]);
        $episode = $this->get("/admin/media-contents/create?podcast={$program->id}")->assertOk()->getContent();
        $this->assertTrue($lit($episode, 'admin.programs.index'));
        $this->assertFalse($lit($episode, 'admin.music.index'));

        $music = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertTrue($lit($music, 'admin.music.index'));
    }

    public function test_the_menu_is_hidden_and_the_page_forbidden_without_media_permission(): void
    {
        $this->signIn(['view-programs', 'create-programs', 'edit-programs', 'delete-programs']);

        $this->get('/admin/programs')->assertOk()->assertDontSee(route('admin.music.index'));
        $this->get('/admin/music')->assertForbidden();
    }

    public function test_the_page_lists_music_news_health_and_economy_but_not_podcasts(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $this->track('An Ambient Track', 'ambient-music');
        $this->track('A News Story', 'news');
        $this->track('A Health Tip', 'health');
        $this->track('A Market Note', 'economyfinance');
        $this->track('A Podcast Episode', 'podcast');

        $this->get('/admin/music')
            ->assertOk()
            ->assertSee('A Song')
            ->assertSee('An Ambient Track')
            ->assertSee('A News Story')
            ->assertSee('A Health Tip')
            ->assertSee('A Market Note')
            ->assertDontSee('A Podcast Episode');
    }

    public function test_tabs_filter_by_category_and_an_unknown_one_means_all(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $this->track('An Old Ambient Track', 'ambient-music');
        $this->track('A News Story', 'news');

        $html = $this->get('/admin/music')->assertOk()->getContent();
        // The tabs, in order: All, Song, News, Health, Economy/Finance.
        $order = array_map(fn ($slug) => strpos($html, 'name="category" value="'.$slug.'"'), ['song', 'news', 'health', 'economyfinance']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        // Ambient tracks are songs with moods, and podcasts belong to Programs: neither has a tab.
        $this->assertStringNotContainsString('name="category" value="ambient-music"', $html);
        $this->assertStringNotContainsString('>Ambient Music</button>', $html);
        $this->assertStringNotContainsString('name="category" value="podcast"', $html);
        $this->assertMatchesRegularExpression('/value="" [^>]*class="active">All</', $html);

        $this->get('/admin/music?category=song')
            ->assertOk()
            ->assertSee('A Song')
            ->assertDontSee('A News Story')
            ->assertDontSee('An Old Ambient Track');
        // Anything that isn't a tab doesn't narrow the list; anything still filed under Ambient Music shows in All.
        foreach (['/admin/music', '/admin/music?category=ambient-music', '/admin/music?category=podcast'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('A Song')
                ->assertSee('A News Story')
                ->assertSee('An Old Ambient Track');
        }
    }

    public function test_the_list_is_a_datatable_over_every_row_of_the_tab(): void
    {
        $this->signIn();
        // More than the old 15 a page: every row is on the page, and DataTables does the paging.
        foreach (range(1, 20) as $n) {
            Storage::disk('public')->put("songs/track-{$n}.mp3", str_repeat('a', $n * 1000));
            $this->track("Track number {$n}", 'song', ['stream' => "songs/track-{$n}.mp3", 'play_count' => $n, 'published_at' => now()->subDays($n)]);
        }

        $html = $this->get('/admin/music')->assertOk()->getContent();

        $this->assertSame(20, substr_count($html, 'Track number '));
        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringContainsString('<table class="data-table" id="article-table">', $html);
        $this->assertStringContainsString('/ajax/libs/jquery/', $html);
        $this->assertStringContainsString('/ajax/libs/datatables.net/', $html);
        $this->assertStringContainsString("jQuery('#article-table').DataTable(", $html);
        // Raw values to sort by, since the cells show "9.9 MB", "1,234" and a date.
        $this->assertStringContainsString('data-order="5000"', $html);
        $this->assertMatchesRegularExpression('/data-order="\d{9,}"/', $html);
        // The Actions column can't be sorted or searched.
        $this->assertStringContainsString('targets: -1, orderable: false, searchable: false', $html);
    }

    public function test_an_empty_tab_says_so(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');

        $this->get('/admin/music?category=health')
            ->assertOk()
            ->assertSee('Nothing in Health yet.');
    }

    public function test_new_song_opens_the_form_on_the_song_category(): void
    {
        $this->signIn();
        $song = $this->category('song');

        $html = $this->get('/admin/music')->assertOk()->getContent();
        // A plain button, not a drop-down.
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(e(route('admin.media-contents.create', ['category' => 'song', 'from' => 'music'])), '/').'" class="primary-button">New Song /',
            $html,
        );
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('Select category', $html);
        // Ambient Music is picked with moods on the form, so "New +" doesn't offer it as a category.
        $this->assertStringNotContainsString(
            e(route('admin.media-contents.create', ['category' => 'ambient-music', 'from' => 'music'])),
            $html,
        );

        $form = $this->get('/admin/media-contents/create?category=song&from=music')
            ->assertOk()
            ->assertSee('name="from" value="music"', false)
            ->assertSee(route('admin.music.index'), false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$song->id.'"[^>]* selected>/s', $form);
    }

    public function test_the_new_button_follows_the_tab(): void
    {
        $this->signIn();
        $link = fn (string $slug) => e(route('admin.media-contents.create', ['category' => $slug, 'from' => 'music']));

        // All starts a Song; each other tab starts its own category.
        $html = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.$link('song').'" class="primary-button">New Song ', $html);
        $html = $this->get('/admin/music?category=health')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.$link('health').'" class="primary-button">New Health ', $html);

        // The form it opens starts on that category and returns here.
        $health = $this->category('health');
        $form = $this->get('/admin/media-contents/create?category=health&from=music')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$health->id.'"[^>]* selected>/s', $form);
    }

    public function test_saving_from_the_music_page_returns_to_it(): void
    {
        $this->signIn();
        $song = $this->category('song');

        $this->post('/admin/media-contents', [
            'from' => 'music',
            'category_id' => $song->id,
            'title' => 'Fresh Track',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));

        $track = MediaContent::where('slug', 'fresh-track')->firstOrFail();

        $this->put("/admin/media-contents/{$track->id}", [
            'from' => 'music',
            'category_id' => $song->id,
            'title' => 'Fresh Track',
            'slug' => 'fresh-track',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));

        $this->delete("/admin/media-contents/{$track->id}", ['from' => 'music'])
            ->assertRedirect(route('admin.music.index'));
        $this->assertDatabaseMissing('media_contents', ['id' => $track->id]);
    }

    public function test_the_media_content_page_keeps_its_own_redirects(): void
    {
        $this->signIn();
        $track = $this->track('Plain Track', 'song');

        $this->delete("/admin/media-contents/{$track->id}")->assertRedirect(route('admin.media-contents.index'));
    }
}
