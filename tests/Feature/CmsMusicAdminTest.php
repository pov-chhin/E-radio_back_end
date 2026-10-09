<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
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

        $html = $this->get('/admin/podcasts')->assertOk()->getContent();

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

        $form = $this->get('/admin/media-contents/create/song')->assertOk();
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

        $this->get('/admin/podcasts')->assertOk()->assertDontSee(route('admin.music.index'));
        $this->get('/admin/music')->assertForbidden();
    }

    public function test_the_page_lists_music_news_health_economy_and_podcast_episodes(): void
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
            ->assertSee('A Podcast Episode');
    }

    public function test_tabs_are_all_song_program_podcasts_and_ambient_music_and_an_unknown_one_means_all(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $this->track('An Old Ambient Track', 'ambient-music');
        $this->track('A News Story', 'news');
        $this->track('A Health Tip', 'health');
        $this->track('A Market Note', 'economyfinance');
        $this->track('A Podcast Episode', 'podcast');

        $html = $this->get('/admin/music')->assertOk()->getContent();
        // The tabs, in order: All, Song, Audio, Podcasts, Ambient Music. News, Health and Economy/Finance are one
        // tab, not three.
        $order = array_map(fn ($key) => strpos($html, 'name="category" value="'.$key.'"'), ['', 'song', 'program', 'podcast', 'ambient']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        foreach (['news', 'health', 'economyfinance', 'ambient-music'] as $slug) {
            $this->assertStringNotContainsString('name="category" value="'.$slug.'"', $html);
        }
        $this->assertMatchesRegularExpression('/value="" [^>]*class="active">All</', $html);
        $this->assertMatchesRegularExpression('/value="program" [^>]*>Audio</', $html);
        $this->assertMatchesRegularExpression('/value="podcast" [^>]*>Podcasts</', $html);
        $this->assertMatchesRegularExpression('/value="ambient" [^>]*>Ambient Music</', $html);

        // Song: songs only.
        $this->get('/admin/music?category=song')
            ->assertOk()
            ->assertSee('A Song')
            ->assertDontSee('An Old Ambient Track')
            ->assertDontSee('A News Story')
            ->assertDontSee('A Health Tip')
            ->assertDontSee('A Market Note')
            ->assertDontSee('A Podcast Episode');

        // Program: News, Health and Economy/Finance together.
        $program = $this->get('/admin/music?category=program')
            ->assertOk()
            ->assertSee('A News Story')
            ->assertSee('A Health Tip')
            ->assertSee('A Market Note')
            ->assertDontSee('A Song')
            ->assertDontSee('An Old Ambient Track')
            ->assertDontSee('A Podcast Episode')
            ->getContent();
        $this->assertMatchesRegularExpression('/value="program" [^>]*class="active">Audio</', $program);

        // Podcasts: the podcast episodes only.
        $podcasts = $this->get('/admin/music?category=podcast')
            ->assertOk()
            ->assertSee('A Podcast Episode')
            ->assertDontSee('A Song')
            ->assertDontSee('A News Story')
            ->assertDontSee('An Old Ambient Track')
            ->getContent();
        $this->assertMatchesRegularExpression('/value="podcast" [^>]*class="active">Podcasts</', $podcasts);

        // Anything that isn't a tab doesn't narrow the list; anything still filed under Ambient Music shows in All.
        foreach (['/admin/music?category=ambient-music', '/admin/music?category=news', '/admin/music?category[]=song'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('A Song')
                ->assertSee('An Old Ambient Track')
                ->assertSee('A News Story');
        }
    }

    public function test_the_ambient_music_tab_lists_whatever_carries_a_mood(): void
    {
        $this->signIn();
        $mood = Tag::where('category_id', $this->category('ambient-music')->id)->whereNotNull('parent_id')->where('slug', 'yoga')->firstOrFail();
        $genre = Tag::where('category_id', $this->category('song')->id)->whereNotNull('parent_id')->firstOrFail();

        $this->track('Calm Song', 'song')->tags()->sync([$mood->id]);
        $this->track('Calm Health Talk', 'health')->tags()->sync([$mood->id]);
        $this->track('Genre Song', 'song')->tags()->sync([$genre->id]);
        $this->track('Plain Song', 'song');
        $this->track('Filed Under Ambient', 'ambient-music');
        $this->track('A Podcast Episode', 'podcast');

        // A mood makes it Ambient Music whatever its category; a genre tag or no tag doesn't. Anything still filed
        // under the Ambient Music category is there too.
        $html = $this->get('/admin/music?category=ambient')
            ->assertOk()
            ->assertSee('Calm Song')
            ->assertSee('Calm Health Talk')
            ->assertSee('Filed Under Ambient')
            ->assertDontSee('Genre Song')
            ->assertDontSee('Plain Song')
            ->assertDontSee('A Podcast Episode')
            ->getContent();
        $this->assertMatchesRegularExpression('/value="ambient" [^>]*class="active">Ambient Music</', $html);
        $this->assertSame(1, substr_count($html, 'Calm Song'), 'a track with several moods or tags is listed once');

        // Its New button opens the Ambient Music form.
        $link = e(route('admin.media-contents.create', ['category' => 'ambient-music']));
        $this->assertStringContainsString('href="'.$link.'" class="primary-button">New Ambient Music ', $html);

        // All still lists each of them once.
        $all = $this->get('/admin/music')->assertOk()->assertSee('Calm Song')->assertSee('Filed Under Ambient')->getContent();
        $this->assertSame(1, substr_count($all, 'Calm Song'));
    }

    public function test_new_ambient_music_fixes_the_category_on_ambient_music_and_hides_podcast(): void
    {
        $this->signIn();
        $ambient = $this->category('ambient-music');

        $form = $this->get('/admin/media-contents/create/ambient-music')->assertOk()->getContent();

        // Category: Ambient Music, selected, and not changeable (a disabled select; a hidden field posts it).
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$ambient->id.'">', $form);
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+disabled\s*>/', $form);
        $this->assertDoesNotMatchRegularExpression('/<select id="category_id"[^>]*name="category_id"/', $form);
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$ambient->id.'"[^>]* selected>Ambient Music<\/option>/s', $form);
        preg_match('/<select id="category_id".*?<\/select>/s', $form, $select);
        $this->assertSame(1, substr_count($select[0], '<option value="'.$ambient->id.'"'));
        $this->assertSame(2, substr_count($select[0], '<option '), 'only the placeholder and Ambient Music are in the list');

        // No Podcast field; the Artist field is still there, and the moods are ticked in the Tags box (no card).
        foreach (['id="program_id"', 'name="program_id"', 'No podcast'] as $gone) {
            $this->assertStringNotContainsString($gone, $form);
        }
        $this->assertStringContainsString('id="artist_id" name="artist_id"', $form);
        $this->assertStringNotContainsString('data-ambient-card', $form);
        $moods = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->get();
        foreach ($moods as $mood) {
            $this->assertStringContainsString('name="tags[]" value="'.$mood->id.'"', $form);
        }

        // The other forms still keep Ambient Music out of the Category list (it is picked with the moods card).
        foreach (['/admin/media-contents/create?category=ambient-music', '/admin/media-contents/create/song', '/admin/media-contents/create/news'] as $url) {
            $this->assertDoesNotMatchRegularExpression('/<option value="'.$ambient->id.'"/', $this->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function test_an_ambient_music_item_saved_from_the_locked_form_is_filed_under_ambient_music(): void
    {
        $this->signIn();
        $ambient = $this->category('ambient-music');
        $mood = Tag::where('category_id', $ambient->id)->whereNotNull('parent_id')->where('slug', 'yoga')->firstOrFail();

        // What the locked form posts: the hidden category, a mood from the card, and no program_id.
        $this->post('/admin/media-contents', [
            'from' => 'music',
            'category_id' => $ambient->id,
            'title' => 'Calm Waves',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$mood->id],
        ])->assertRedirect(route('admin.music.index'));

        $saved = MediaContent::where('slug', 'calm-waves')->firstOrFail();
        $this->assertSame($ambient->id, $saved->category_id);
        $this->assertNull($saved->program_id);
        $this->assertSame([$mood->id], $saved->tags()->pluck('tags.id')->all());
        $this->get('/admin/music?category=ambient')->assertOk()->assertSee('Calm Waves');
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

    public function test_an_empty_tab_says_so_and_is_still_a_datatable(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');

        $html = $this->get('/admin/music?category=program')
            ->assertOk()
            ->assertSee('Nothing in Audio yet.')
            ->getContent();

        // No hand-made "empty" row: DataTables itself shows the message, so the search box and paging are there.
        $this->assertStringNotContainsString('empty-row', $html);
        $this->assertStringContainsString("jQuery('#article-table').DataTable(", $html);
        $this->assertStringContainsString("emptyTable: 'Nothing in Audio yet.'", $html);
        // ...centred across the table, as the old empty row was.
        $this->assertMatchesRegularExpression('/#article-table td\.dt-empty \{[^}]*text-align: center/', $html);

        // Nothing at all, on All.
        MediaContent::query()->delete();
        $all = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString("emptyTable: 'Nothing here yet.'", $all);
        $this->assertStringContainsString("jQuery('#article-table').DataTable(", $all);
    }

    public function test_all_names_each_rows_group_while_a_tab_shows_its_category(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $this->track('An Old Ambient Track', 'ambient-music');
        $this->track('A News Story', 'news');
        $this->track('A Health Tip', 'health');
        $this->track('A Market Note', 'economyfinance');
        $this->track('A Podcast Episode', 'podcast');
        $cell = fn (string $html, string $title) => preg_match('/'.preg_quote($title, '/').'<\/b>.*?<td class="muted">([^<]*)<\/td>/s', $html, $m) ? trim($m[1]) : null;

        // All: Song, Audio (for News, Health and Economy/Finance), Podcasts, Ambient Music.
        $all = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertSame('Song', $cell($all, 'A Song'));
        $this->assertSame('Ambient Music', $cell($all, 'An Old Ambient Track'));
        $this->assertSame('Audio', $cell($all, 'A News Story'));
        $this->assertSame('Audio', $cell($all, 'A Health Tip'));
        $this->assertSame('Audio', $cell($all, 'A Market Note'));
        $this->assertSame('Podcasts', $cell($all, 'A Podcast Episode'));
        foreach (['News', 'Health', 'Economy/Finance'] as $name) {
            $this->assertStringNotContainsString('<td class="muted">'.$name.'</td>', $all);
        }

        // On the Program tab the category says which of the three it is.
        $program = $this->get('/admin/music?category=program')->assertOk()->getContent();
        $this->assertSame('News', $cell($program, 'A News Story'));
        $this->assertSame('Health', $cell($program, 'A Health Tip'));
        $this->assertSame('Economy/Finance', $cell($program, 'A Market Note'));
    }

    public function test_new_song_opens_the_form_on_the_song_category(): void
    {
        $this->signIn();
        $song = $this->category('song');

        $html = $this->get('/admin/music')->assertOk()->getContent();
        // A plain button, not a drop-down.
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(e(route('admin.media-contents.create', ['category' => 'song'])), '/').'" class="primary-button">New Song /',
            $html,
        );
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('Select category', $html);
        // Ambient Music is picked with moods on the form, so "New +" doesn't offer it as a category.
        $this->assertStringNotContainsString(
            e(route('admin.media-contents.create', ['category' => 'ambient-music'])),
            $html,
        );

        $form = $this->get('/admin/media-contents/create/song')
            ->assertOk()
            ->assertSee('name="from" value="music"', false)
            ->assertSee(route('admin.music.index'), false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$song->id.'"[^>]* selected>/s', $form);

        // New Song fixes the category on Song (a disabled select, with a hidden field posting it instead) and
        // hides the podcast field altogether.
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$song->id.'">', $form);
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+disabled\s*>/', $form);
        $this->assertDoesNotMatchRegularExpression('/<select id="category_id"[^>]*name="category_id"/', $form);
        $this->assertStringNotContainsString('id="program_id"', $form);
        $this->assertStringNotContainsString('name="program_id"', $form);
        $this->assertStringNotContainsString('No podcast', $form);
    }

    public function test_new_program_offers_news_health_and_economy_and_leaves_off_podcast_and_artist(): void
    {
        $this->signIn();
        $options = fn (string $form) => array_map(
            fn ($label) => trim($label),
            preg_match('/<select id="category_id".*?<\/select>/s', $form, $m) ? (preg_match_all('/<option value="\d*"[^>]*>([^<]*)<\/option>/', $m[0], $o) ? $o[1] : []) : [],
        );

        $form = $this->get('/admin/media-contents/create/news')->assertOk()->getContent();

        // Category: still a normal select (it can change), but only the Program categories, News first.
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $form);
        $this->assertStringNotContainsString('<input type="hidden" name="category_id"', $form);
        $this->assertEqualsCanonicalizing(['Select a category…', 'News', 'Health', 'Economy/Finance'], $options($form));
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$this->category('news')->id.'"[^>]* selected>/s', $form);

        // No Podcast and no Artist field.
        foreach (['id="program_id"', 'name="program_id"', 'No podcast', 'id="artist_id"', 'name="artist_id"', 'No artist'] as $gone) {
            $this->assertStringNotContainsString($gone, $form);
        }
        $this->assertStringContainsString('id="program_id"', $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent());
    }

    public function test_the_podcast_field_has_a_red_new_button_that_opens_the_new_podcast_page(): void
    {
        $this->signIn();
        $create = e(route('admin.programs.create'));

        // New Podcast (and the plain media form): a compact red button on the Podcast label's row, in a new tab.
        foreach (['/admin/media-contents/create/podcast', '/admin/media-contents/create'] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/<label>\s*<span[^>]*>\s*Podcast\s*<a href="'.preg_quote($create, '/').'" target="_blank" rel="noopener" class="primary-button compact"[^>]*>New<\/a>\s*<\/span>\s*<select id="program_id"/s',
                $form,
                $url,
            );
        }

        // The forms that leave the Podcast field off have no button.
        foreach (['song', 'news', 'ambient-music'] as $slug) {
            $this->assertStringNotContainsString($create, $this->get("/admin/media-contents/create/{$slug}")->assertOk()->getContent());
        }
    }

    public function test_new_podcast_has_no_no_podcast_option_but_the_other_forms_keep_it(): void
    {
        $this->signIn();
        $podcast = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $select = fn (string $form) => substr($form, $start = strpos($form, '<select id="program_id"'), strpos($form, '</select>', $start) - $start);

        // New Podcast: a placeholder that shows until one is picked (not pickable again), then just the podcasts.
        $form = $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent();
        $this->assertStringNotContainsString('No podcast', $form);
        $this->assertMatchesRegularExpression('/<option value="" disabled hidden selected>Select a podcast…<\/option>/', $select($form));
        $this->assertStringContainsString('>Morning Brief</option>', $select($form));

        // It starts on a podcast when one is given (an episode added from a podcast's own page keeps working).
        $fromShow = $this->get("/admin/media-contents/create/podcast?podcast={$podcast->id}")->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<option value="" disabled hidden selected>/', $select($fromShow));
        $this->assertMatchesRegularExpression('/<option value="'.$podcast->id.'"[^>]* selected>Morning Brief<\/option>/', $select($fromShow));

        // Everywhere else the field can still be cleared: the plain form, an edit, a form opened from a podcast page.
        $track = $this->track('Plain Track', 'song');
        foreach (['/admin/media-contents/create', "/admin/media-contents/{$track->id}/edit", "/admin/media-contents/create?podcast={$podcast->id}"] as $url) {
            $other = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<option value="">No podcast</option>', $select($other), $url);
            $this->assertStringNotContainsString('Select a podcast…', $other, $url);
        }
    }

    public function test_a_podcast_episode_saved_from_the_new_podcast_form_keeps_its_podcast(): void
    {
        $this->signIn();
        $podcast = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);

        $this->post('/admin/media-contents', [
            'from' => 'music',
            'category_id' => $this->category('podcast')->id,
            'program_id' => $podcast->id,
            'title' => 'Episode One',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));

        $this->assertSame($podcast->id, MediaContent::where('slug', 'episode-one')->firstOrFail()->program_id);
        $this->get('/admin/music?category=podcast')->assertOk()->assertSee('Episode One');
    }

    public function test_a_new_items_title_names_its_category(): void
    {
        $this->signIn();
        $titles = fn (string $html) => [
            preg_match('/<div class="breadcrumbs">.*?<strong>([^<]*)<\/strong>/s', $html, $c) ? $c[1] : null,
            preg_match('/<strong class="editor-topbar-title"[^>]*>\s*([^<]*?)\s*<\/strong>/s', $html, $t) ? $t[1] : null,
            preg_match('/<title>([^<]*)<\/title>/', $html, $p) ? $p[1] : null,
        ];

        // "New" + the category + "Media Content", in the page header, the top bar and the browser tab.
        foreach ([
            'song' => 'New Song Media Content',
            'news' => 'New News Media Content',
            'podcast' => 'New Podcast Media Content',
            'ambient-music' => 'New Ambient Music Media Content',
        ] as $slug => $expected) {
            [$crumb, $bar, $tab] = $titles($this->get("/admin/media-contents/create/{$slug}")->assertOk()->getContent());
            $this->assertSame($expected, $crumb, $slug);
            $this->assertSame($expected, $bar, $slug);
            $this->assertStringStartsWith($expected.' · ', $tab, $slug);
        }

        // No category yet: as before.
        $plain = $this->get('/admin/media-contents/create')->assertOk()->getContent();
        $this->assertSame(['New Media Content', 'New media content'], array_slice($titles($plain), 0, 2));

        // An episode added from a podcast's own page, and editing, are untouched.
        $program = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $episode = $titles($this->get("/admin/media-contents/create?podcast={$program->id}")->assertOk()->getContent());
        $this->assertSame(['New Media Content', 'New episode'], array_slice($episode, 0, 2));
        $track = $this->track('Plain Track', 'song');
        // Editing from the Article page names the category like its New button; the plain edit address doesn't.
        // The top bar keeps the item's own title either way.
        $edit = $titles($this->get("/admin/media-contents/edit/song/{$track->id}")->assertOk()->getContent());
        $this->assertSame(['Edit Song Media Content', 'Plain Track'], array_slice($edit, 0, 2));
        $this->assertStringStartsWith('Edit Song Media Content · ', $edit[2]);
        $plainEdit = $titles($this->get("/admin/media-contents/{$track->id}/edit")->assertOk()->getContent());
        $this->assertSame(['Edit Media Content', 'Plain Track'], array_slice($plainEdit, 0, 2));
    }

    public function test_the_new_items_title_carries_a_template_so_the_category_select_can_rename_it(): void
    {
        $this->signIn();

        $form = $this->get('/admin/media-contents/create/news')->assertOk()->getContent();
        $this->assertStringContainsString('data-title-template="New :category Media Content"', $form);
        $this->assertStringContainsString('data-title-plain="New media content"', $form);
        $this->assertStringContainsString('data-title-plain-crumb="New Media Content"', $form);

        // Not on an edit form or an episode form, whose titles aren't "New …" (the attribute, not the script's selector).
        $track = $this->track('Plain Track', 'song');
        $this->assertStringNotContainsString(' data-title-template="', $this->get("/admin/media-contents/{$track->id}/edit")->assertOk()->getContent());
        $program = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $this->assertStringNotContainsString(' data-title-template="', $this->get("/admin/media-contents/create?podcast={$program->id}")->assertOk()->getContent());
    }

    public function test_the_category_is_in_the_path_of_the_create_url_and_the_old_query_form_still_works(): void
    {
        $this->signIn();
        $song = $this->category('song');

        // The URL a link gets: the category in the path, the rest in the query.
        $this->assertSame('/admin/media-contents/create/song', route('admin.media-contents.create', ['category' => 'song'], false));
        $this->assertSame('/admin/media-contents/create', route('admin.media-contents.create', [], false));
        $this->assertSame('/admin/media-contents/create?podcast=5', route('admin.media-contents.create', ['podcast' => 5], false));

        // /create/song and the older /create?category=song open the same form: Song fixed, no Podcast field.
        foreach (['/admin/media-contents/create/song', '/admin/media-contents/create?category=song&from=music'] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$song->id.'">', $form, $url);
            $this->assertStringNotContainsString('id="program_id"', $form, $url);
            $this->assertStringContainsString('New Song Media Content', $form, $url);
        }
        // A category in the path is the Article page's button, so it needs no ?from=music: the form goes back to the
        // Article page and posts the flag that sends a save there. A bare ?category=song is the whole form.
        foreach (['song', 'news', 'podcast', 'ambient-music', 'nope'] as $slug) {
            $viaPath = $this->get("/admin/media-contents/create/{$slug}")->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="Back to articles"', $viaPath, $slug);
            $this->assertStringContainsString('<input type="hidden" name="from" value="music">', $viaPath, $slug);
        }
        $bare = $this->get('/admin/media-contents/create?category=song')->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Back to media content"', $bare);
        $this->assertStringNotContainsString('<input type="hidden" name="from" value="music">', $bare);
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $bare);

        // The path wins over a query that disagrees.
        $mixed = $this->get('/admin/media-contents/create/song?category=news')->assertOk()->getContent();
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$song->id.'">', $mixed);

        // The Article page's New buttons link to the path form, with no ?from=music tacked on.
        $articlePage = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.e(url('/admin/media-contents/create/song')).'"', $articlePage);
        $this->assertStringNotContainsString('/create/song?from=music', $articlePage);
        $this->assertStringNotContainsString('/create?category=', $articlePage);
        foreach (['program' => 'news', 'podcast' => 'podcast', 'ambient' => 'ambient-music'] as $tab => $slug) {
            $this->assertStringContainsString(
                'href="'.e(url("/admin/media-contents/create/{$slug}")).'" class="primary-button">New ',
                $this->get("/admin/music?category={$tab}")->assertOk()->getContent(),
                $tab,
            );
        }

        // An unknown category just starts with none picked, as the query form did; a malformed one or an extra
        // segment isn't a route.
        $this->get('/admin/media-contents/create/nope')->assertOk()->assertSee('New media content');
        $this->get('/admin/media-contents/create/Song')->assertNotFound();
        $this->get('/admin/media-contents/create/song/extra')->assertNotFound();
        // Editing is untouched by the new segment.
        $track = $this->track('Plain Track', 'song');
        $this->get("/admin/media-contents/{$track->id}/edit")->assertOk();
    }

    public function test_the_article_pages_edit_link_is_edit_category_id_with_no_query(): void
    {
        $this->signIn();
        $news = $this->track('A News Story', 'news');
        $song = $this->track('A Song', 'song');
        $ambient = $this->track('An Old Ambient Track', 'ambient-music');

        // The URL a link gets: the item's category and its id in the path.
        $this->assertSame("/admin/media-contents/edit/news/{$news->id}", route('admin.media-contents.edit-in', ['category' => 'news', 'media_content' => $news], false));

        // The Article page links each row to its own category's address, with no ?from=music.
        $page = $this->get('/admin/music')->assertOk()->getContent();
        foreach ([[$news, 'news'], [$song, 'song'], [$ambient, 'ambient-music']] as [$item, $slug]) {
            $this->assertStringContainsString('href="'.e(url("/admin/media-contents/edit/{$slug}/{$item->id}")).'"', $page, $slug);
            $this->assertStringNotContainsString("/media-contents/{$item->id}/edit", $page, $slug);
        }
        $this->assertStringNotContainsString('/edit?from=music', $page);

        // The path form is the Article page's: back arrow to it, and the flag that sends a save there.
        $form = $this->get("/admin/media-contents/edit/news/{$news->id}")->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Back to articles"', $form);
        $this->assertStringContainsString('<input type="hidden" name="from" value="music">', $form);
        $this->assertStringContainsString('action="'.e(route('admin.media-contents.update', $news)).'"', $form);
        $this->assertStringContainsString('A News Story', $form);

        // The plain /{id}/edit is the whole form, going back to the media list; the old ?from=music still works.
        $plain = $this->get("/admin/media-contents/{$news->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Back to media content"', $plain);
        $this->assertStringNotContainsString('<input type="hidden" name="from" value="music">', $plain);
        $this->assertStringContainsString('aria-label="Back to articles"', $this->get("/admin/media-contents/{$news->id}/edit?from=music")->assertOk()->getContent());

        // Saving from the path form goes back to the Article page.
        $this->put("/admin/media-contents/{$news->id}", [
            'from' => 'music',
            'category_id' => $news->category_id,
            'title' => 'A News Story',
            'slug' => $news->slug,
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));
    }

    public function test_editing_from_the_article_page_is_the_same_form_as_its_new_button(): void
    {
        $this->signIn();
        $program = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $categoryOptions = fn (string $form) => preg_match('/<select id="category_id".*?<\/select>/s', $form, $m)
            ? array_map('trim', preg_match_all('/<option value="\d*"[^>]*>([^<]*)<\/option>/', $m[0], $o) ? $o[1] : [])
            : [];
        $locked = fn (string $form, int $id) => str_contains($form, '<input type="hidden" name="category_id" value="'.$id.'">')
            && preg_match('/<select id="category_id"\s+disabled\s*>/', $form) === 1;

        // Song: Song fixed, no Podcast, Artist still there — like New Song.
        $song = $this->track('A Song', 'song');
        $form = $this->get("/admin/media-contents/edit/song/{$song->id}")->assertOk()->getContent();
        $this->assertTrue($locked($form, $song->category_id));
        $this->assertStringNotContainsString('id="program_id"', $form);
        $this->assertStringContainsString('id="artist_id" name="artist_id"', $form);
        $this->assertStringContainsString('Edit Song Media Content', $form);

        // Program (News, Health, Economy/Finance): just those three to pick from, no Podcast, no Artist — like New Program.
        foreach (['news' => 'News', 'health' => 'Health', 'economyfinance' => 'Economy/Finance'] as $slug => $name) {
            $item = $this->track("A {$name} Item", $slug);
            $form = $this->get("/admin/media-contents/edit/{$slug}/{$item->id}")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $form, $slug);
            $this->assertEqualsCanonicalizing(['Select a category…', 'News', 'Health', 'Economy/Finance'], $categoryOptions($form), $slug);
            $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$item->category_id.'"[^>]* selected>'.preg_quote($name, '/').'</s', $form, $slug);
            foreach (['id="program_id"', 'name="program_id"', 'id="artist_id"', 'name="artist_id"', 'No podcast', 'No artist'] as $gone) {
                $this->assertStringNotContainsString($gone, $form, "{$slug} {$gone}");
            }
            $this->assertStringContainsString("Edit {$name} Media Content", $form, $slug);
        }

        // Ambient Music: fixed on it, no Podcast — like New Ambient Music.
        $ambient = $this->track('An Old Ambient Track', 'ambient-music');
        $form = $this->get("/admin/media-contents/edit/ambient-music/{$ambient->id}")->assertOk()->getContent();
        $this->assertTrue($locked($form, $ambient->category_id));
        $this->assertStringNotContainsString('id="program_id"', $form);
        $this->assertStringContainsString('Edit Ambient Music Media Content', $form);

        // Podcast: fixed on Podcast, the Podcast field has no "No podcast", and its New button is there — like New
        // Podcast. The item's own podcast is the one picked.
        $episode = $this->track('Episode One', 'podcast', ['program_id' => $program->id]);
        $form = $this->get("/admin/media-contents/edit/podcast/{$episode->id}")->assertOk()->getContent();
        $this->assertTrue($locked($form, $episode->category_id));
        $this->assertStringNotContainsString('No podcast', $form);
        $this->assertMatchesRegularExpression('/<option value="'.$program->id.'"[^>]* selected>Morning Brief<\/option>/', $form);
        $this->assertStringContainsString(e(route('admin.programs.create')), $form);
        $this->assertStringContainsString('Edit Podcast Media Content', $form);
        // One filed under Podcast with no podcast yet shows the placeholder.
        $orphan = $this->track('Orphan Episode', 'podcast');
        $this->assertMatchesRegularExpression('/<option value="" disabled hidden selected>Select a podcast…<\/option>/', $this->get("/admin/media-contents/edit/podcast/{$orphan->id}")->getContent());

        // The plain edit address is still the whole form.
        $whole = $this->get("/admin/media-contents/{$song->id}/edit")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $whole);
        $this->assertStringContainsString('id="program_id" name="program_id"', $whole);
        $this->assertStringContainsString('id="artist_id" name="artist_id"', $whole);
        $this->assertStringContainsString('<option value="">No podcast</option>', $whole);
    }

    public function test_saving_from_the_article_edit_form_keeps_the_fields_it_does_not_show(): void
    {
        $this->signIn();
        $program = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $artist = \Src\Catalog\Models\Artist::create(['name' => 'Some Artist', 'slug' => 'some-artist']);
        $other = \Src\Catalog\Models\Artist::create(['name' => 'Other Artist', 'slug' => 'other-artist']);
        $payload = fn (MediaContent $item, array $extra = []) => [
            'from' => 'music', 'category_id' => $item->category_id, 'title' => $item->title, 'slug' => $item->slug,
            'media_type' => 'audio', 'status' => 'private', ...$extra,
        ];

        // A news item with a podcast and an artist: its form shows neither, so a save leaves both.
        $news = $this->track('A News Story', 'news', ['program_id' => $program->id, 'artist_id' => $artist->id]);
        $this->put("/admin/media-contents/{$news->id}", $payload($news, ['title' => 'A News Story, edited']))->assertRedirect(route('admin.music.index'));
        $news->refresh();
        $this->assertSame('A News Story, edited', $news->title);
        $this->assertSame([$program->id, $artist->id], [$news->program_id, $news->artist_id]);

        // A song with a podcast: Podcast is off its form (kept), Artist is on it (what is posted wins).
        $song = $this->track('A Song', 'song', ['program_id' => $program->id, 'artist_id' => $artist->id]);
        $this->put("/admin/media-contents/{$song->id}", $payload($song, ['artist_id' => $other->id]))->assertRedirect(route('admin.music.index'));
        $song->refresh();
        $this->assertSame([$program->id, $other->id], [$song->program_id, $song->artist_id]);
        $this->put("/admin/media-contents/{$song->id}", $payload($song))->assertRedirect(route('admin.music.index'));
        $this->assertSame([$program->id, null], [$song->fresh()->program_id, $song->fresh()->artist_id]);

        // A podcast episode's form shows both, so what is posted wins there; and the plain edit form clears as before.
        $episode = $this->track('Episode One', 'podcast', ['program_id' => $program->id, 'artist_id' => $artist->id]);
        $this->put("/admin/media-contents/{$episode->id}", $payload($episode, ['program_id' => $program->id]))->assertRedirect(route('admin.music.index'));
        $this->assertSame([$program->id, null], [$episode->fresh()->program_id, $episode->fresh()->artist_id]);
        $plain = $this->track('Plain News', 'news', ['program_id' => $program->id, 'artist_id' => $artist->id]);
        $this->put("/admin/media-contents/{$plain->id}", $payload($plain, ['from' => null]))->assertRedirect(route('admin.media-contents.index'));
        $this->assertSame([null, null], [$plain->fresh()->program_id, $plain->fresh()->artist_id]);
    }

    public function test_the_description_card_is_set_to_grow_to_the_height_of_the_sidebar(): void
    {
        $this->signIn();
        $track = $this->track('A Song', 'song');

        // The same card on the new form and the edit form: the main column is as tall as the sidebar, the card takes
        // the room left, the editor fills it. (Checked in a real browser: its bottom edge meets the Tags card's.)
        foreach (['/admin/media-contents/create/song', "/admin/media-contents/edit/song/{$track->id}", "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<div class="editor-block description-grow">', $html, $url);
            // The sidebar is its full height — no scroll box of its own — so the page scrolls and Tags is never cut off.
            $this->assertStringContainsString('.editor-layout > .editor-sidebar { position: static; max-height: none; overflow: visible; }', $html, $url);
            $this->assertStringContainsString('.editor-layout > .editor-main { align-self: stretch; }', $html, $url);
            $this->assertStringContainsString('.editor-block.description-grow { flex: 1 1 auto; display: flex; flex-direction: column; }', $html, $url);
            $this->assertStringContainsString('.description-grow .rich-editor .ql-editor { flex: 1 1 auto; height: auto; }', $html, $url);
            // It is the Description card that grows, not the Content card above it.
            $this->assertSame(1, substr_count($html, 'description-grow">'), $url);
        }
    }

    public function test_an_edit_url_with_the_wrong_category_goes_to_the_right_one_and_bad_ones_are_not_found(): void
    {
        $this->signIn();
        $news = $this->track('A News Story', 'news');

        // The category in the path must be the item's own: otherwise to the right address, keeping the query.
        $this->get("/admin/media-contents/edit/song/{$news->id}")
            ->assertRedirect("/admin/media-contents/edit/news/{$news->id}");
        $this->get("/admin/media-contents/edit/nope/{$news->id}?podcast=3")
            ->assertRedirect("/admin/media-contents/edit/news/{$news->id}?podcast=3");

        // Not an item, not an id, not a slug, not a route.
        $this->get('/admin/media-contents/edit/news/99999')->assertNotFound();
        $this->get('/admin/media-contents/edit/news/abc')->assertNotFound();
        $this->get("/admin/media-contents/edit/News/{$news->id}")->assertNotFound();
        $this->get("/admin/media-contents/edit/news/{$news->id}/extra")->assertNotFound();
    }

    public function test_the_edit_url_with_a_category_needs_the_same_permission_as_the_plain_one(): void
    {
        $this->signIn(['view-media-content', 'create-media-content']);
        $news = $this->track('A News Story', 'news');

        $this->get("/admin/media-contents/{$news->id}/edit")->assertForbidden();
        $this->get("/admin/media-contents/edit/news/{$news->id}")->assertForbidden();
        // Even to be sent to the right address.
        $this->get("/admin/media-contents/edit/song/{$news->id}")->assertForbidden();
    }

    public function test_the_create_url_with_a_category_needs_the_same_permission_as_the_plain_one(): void
    {
        $this->signIn(['view-media-content']);

        $this->get('/admin/media-contents/create')->assertForbidden();
        $this->get('/admin/media-contents/create/song')->assertForbidden();
    }

    public function test_the_new_podcast_button_is_left_off_for_an_admin_who_cannot_create_podcasts(): void
    {
        $this->signIn(['view-programs', 'view-media-content', 'create-media-content', 'edit-media-content']);

        $form = $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent();

        // The Podcast field is still there to pick from, just without the way to make a new one.
        $this->assertStringContainsString('id="program_id" name="program_id"', $form);
        $this->assertStringNotContainsString(e(route('admin.programs.create')), $form);
    }

    public function test_new_podcast_fixes_the_category_on_podcast(): void
    {
        $this->signIn();
        $podcast = $this->category('podcast');

        $form = $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent();

        // Podcast is shown but can't be changed: a disabled select, with a hidden field posting it instead.
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$podcast->id.'">', $form);
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+disabled\s*>/', $form);
        $this->assertDoesNotMatchRegularExpression('/<select id="category_id"[^>]*name="category_id"/', $form);
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$podcast->id.'"[^>]* selected>Podcast<\/option>/s', $form);
        preg_match('/<select id="category_id".*?<\/select>/s', $form, $select);
        $this->assertSame(1, substr_count($select[0], '<option value="'.$podcast->id.'"'));
        // The other categories are still in the list for the page's script, but Podcast is the one selected and it can't change.
        $this->assertStringContainsString('New Podcast Media Content', $form);

        // The Podcast field is still the one for picking the show (no "No podcast"), with its New button.
        $this->assertStringContainsString('id="program_id" name="program_id"', $form);
        $this->assertStringNotContainsString('No podcast', $form);
        $this->assertStringContainsString(e(route('admin.programs.create')), $form);

        // What the fixed form posts saves the episode under Podcast with its show.
        $program = Program::create(['category_id' => $podcast->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $this->post('/admin/media-contents', [
            'from' => 'music', 'category_id' => $podcast->id, 'program_id' => $program->id,
            'title' => 'Episode One', 'slug' => '', 'media_type' => 'audio', 'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));
        $saved = MediaContent::where('slug', 'episode-one')->firstOrFail();
        $this->assertSame([$podcast->id, $program->id], [$saved->category_id, $saved->program_id]);
    }

    public function test_the_forms_outside_the_new_buttons_stay_whole(): void
    {
        $this->signIn();
        $track = $this->track('Plain Track', 'song');

        // A song edited at its plain address, a song opened without the page, a program-category form opened
        // without it: every category, with the Podcast and Artist fields.
        foreach ([
            "/admin/media-contents/{$track->id}/edit",
            '/admin/media-contents/create?category=song',
            '/admin/media-contents/create?category=news',
            '/admin/media-contents/create?category[]=news&from=music',
        ] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $form, $url);
            $this->assertStringNotContainsString('<input type="hidden" name="category_id"', $form);
            $this->assertStringContainsString('id="program_id" name="program_id"', $form, $url);
            $this->assertStringContainsString('id="artist_id" name="artist_id"', $form, $url);
            foreach (['Song', 'News', 'Health', 'Economy/Finance', 'Podcast'] as $name) {
                $this->assertStringContainsString(">{$name}</option>", $form, "{$url} offers {$name}");
            }
        }
    }

    public function test_the_tag_box_has_a_region_select_for_international_and_local(): void
    {
        $this->signIn();

        // At the top of the Tags box (there is no search box), on the new form and the edit form alike; a script
        // narrows the tree to the pick. Just the two regions: there is no "all" option.
        $track = $this->track('Plain Track', 'song');
        foreach (['/admin/media-contents/create/song', "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/<h4>Tags<\/h4>\s*<label class="tag-region"[^>]*>\s*<select data-tag-region[^>]*>\s*<option value="international">International<\/option>\s*<option value="local">Local<\/option>\s*<\/select>/s',
                $html,
            );
            $this->assertStringNotContainsString('All regions', $html);
            $this->assertStringNotContainsString('data-tag-search', $html);
            $this->assertStringNotContainsString('Search tags', $html);
            $this->assertStringContainsString("document.querySelector('[data-tag-region]')", $html);
        }
    }

    public function test_a_song_saved_from_the_locked_form_keeps_its_category(): void
    {
        $this->signIn();
        $song = $this->category('song');

        // What the locked form posts: the hidden category, and no program_id (the podcast field isn't on the form).
        $this->post('/admin/media-contents', [
            'from' => 'music',
            'category_id' => $song->id,
            'title' => 'Locked Song',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.music.index'));

        $saved = MediaContent::where('slug', 'locked-song')->firstOrFail();
        $this->assertSame($song->id, $saved->category_id);
        $this->assertNull($saved->program_id);
    }

    public function test_the_new_button_follows_the_tab(): void
    {
        $this->signIn();
        $link = fn (string $slug) => e(route('admin.media-contents.create', ['category' => $slug]));

        // All and Song start a Song; Program starts News (the form's category list picks Health or Economy/Finance);
        // Podcasts starts a Podcast, in the singular.
        foreach (['/admin/music', '/admin/music?category=song'] as $url) {
            $this->get($url)->assertOk()->assertSee('href="'.$link('song').'" class="primary-button">New Song ', false);
        }
        $this->get('/admin/music?category=program')->assertOk()->assertSee('href="'.$link('news').'" class="primary-button">New Audio ', false);
        $this->get('/admin/music?category=podcast')->assertOk()->assertSee('href="'.$link('podcast').'" class="primary-button">New Podcast ', false);

        // The form it opens starts on that category and returns here.
        $news = $this->category('news');
        $form = $this->get('/admin/media-contents/create/news')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$news->id.'"[^>]* selected>/s', $form);
        foreach (['health', 'economyfinance'] as $slug) {
            $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$this->category($slug)->id.'"/s', $form);
        }
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

    public function test_a_long_title_shows_25_letters_and_dots_with_the_full_title_on_hover_and_in_search(): void
    {
        $this->signIn();
        $long = 'ANGKOR WAT, CAMBODIA | Full Travel Guide To Angkor Archeological Park';
        $this->track($long, 'song');
        $this->track('Short Title', 'song');
        $this->track('ក្រុមហ៊ុនទូរគមនាគមន៍កម្ពុជាបានប្រកាសដំណឹងថ្មី', 'news');

        $html = $this->get('/admin/music')->assertOk()->getContent();

        // 25 letters, then "...": the whole title is the tooltip and what the table sorts and searches.
        $this->assertStringContainsString('<b title="'.$long.'">ANGKOR WAT, CAMBODIA | Fu...</b>', $html);
        $this->assertStringContainsString('data-order="'.$long.'" data-search="'.$long.'"', $html);
        // A title that fits is shown as it is, with no tooltip.
        $this->assertStringContainsString('<b >Short Title</b>', $html);
        // Khmer is cut on whole letters, not in the middle of one.
        $this->assertStringContainsString('ក្រុមហ៊ុនទូរគមនាគមន៍កម្ពុជាបានប្រកាសដំណឹ...</b>', $html);
    }
}
