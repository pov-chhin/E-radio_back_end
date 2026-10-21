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

    /** `artist_ids` in the attributes credits those artists on it. */
    private function track(string $title, string $categorySlug, array $attributes = []): MediaContent
    {
        $artistIds = \Illuminate\Support\Arr::pull($attributes, 'artist_ids', []);

        $track = MediaContent::create([
            'category_id' => $this->category($categorySlug)->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            ...$attributes,
        ]);
        $track->artists()->sync($artistIds);

        return $track;
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

    public function test_the_audio_tab_lives_at_category_audio_and_the_old_program_address_redirects_to_it(): void
    {
        $this->signIn();

        $this->get('/admin/music?category=program')->assertRedirectToRoute('admin.music.index', ['category' => 'audio']);

        // The tab is selected at its new address, and its button and heading still say Audio.
        $html = $this->get('/admin/music?category=audio')->assertOk()->getContent();
        $this->assertStringContainsString('name="category" value="audio"', $html);
        $this->assertStringNotContainsString('value="program"', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*value="audio"[^>]*class="[^"]*active[^"]*"[^>]*>\s*Audio\s*</button>#', $html);
    }

    public function test_the_page_and_its_menu_item_are_called_content(): void
    {
        $this->signIn();

        $html = $this->get('/admin/music')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.music.index'), '/').'"[^>]*>\s*<span class="nav-icon[^"]*"><\/span><span>Content<\/span>/', $html);
        $this->assertStringContainsString('<h1>Content</h1>', $html);
        $this->assertStringContainsString('<title>Content', $html);
        $this->assertStringNotContainsString('<h1>Music</h1>', $html);
        $this->assertStringNotContainsString('<span>Music</span>', $html);

        $form = $this->get('/admin/media-contents/create/song')->assertOk();
        $form->assertSee('aria-label="Back to articles"', false);
    }

    public function test_the_title_box_names_the_kind_of_content_the_new_button_started(): void
    {
        $this->signIn();

        foreach ([
            'song' => 'Title Song', 'news' => 'Title Audio', 'ambient-music' => 'Title Ambient', 'podcast' => 'Title Podcasts Episode',
        ] as $category => $placeholder) {
            $this->get("/admin/media-contents/create/{$category}")->assertOk()
                ->assertSee('id="title" name="title" type="text" class="editor-title-input" placeholder="'.$placeholder.'"', false);
        }

        // The whole form (no kind named) isn't one kind, so its box lists them.
        $this->get('/admin/media-contents/create?category=none')->assertOk()
            ->assertSee('placeholder="Title Song or Title Audio or Title Podcasts Episode or Title Ambient"', false);
    }

    public function test_the_media_form_has_no_slug_box_and_the_slug_still_comes_from_the_title(): void
    {
        $this->signIn();
        $track = $this->track('Plain Track', 'song');
        $slug = $track->slug;

        foreach (['/admin/media-contents/create/song', "/admin/media-contents/edit/song/{$track->id}", "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('name="slug"', $html, $url);
            // A thin line right under the title stands where the Slug row's bottom border was.
            $this->assertMatchesRegularExpression('/class="editor-title-input"[^>]*required>\s*<hr class="editor-title-rule"/', $html, $url);
        }

        // A new item posted without one gets its slug from the title; an edit keeps the one it has.
        $this->post('/admin/media-contents', ['category_id' => $this->category('song')->id, 'title' => 'No Slug Box', 'media_type' => 'audio', 'status' => 'private'])->assertRedirect();
        $this->assertSame('no-slug-box', MediaContent::where('title', 'No Slug Box')->firstOrFail()->slug);

        $this->put("/admin/media-contents/{$track->id}", ['category_id' => $track->category_id, 'title' => 'Renamed Track', 'media_type' => 'audio', 'status' => 'private'])->assertRedirect();
        $this->assertSame($slug, $track->fresh()->slug);
    }

    public function test_the_duration_is_shown_as_minutes_and_seconds_in_a_disabled_box_while_the_seconds_are_still_sent(): void
    {
        $this->signIn();
        $track = $this->track('Plain Track', 'song');

        $track->update(['duration_seconds' => 1165]);
        $html = $this->get("/admin/media-contents/{$track->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('<input id="duration_seconds" name="duration_seconds" type="hidden" value="1165">', $html);
        $this->assertStringContainsString('<input type="text" value="19:25" placeholder="0:00" disabled data-duration-display>', $html);
        $this->assertStringNotContainsString('Duration (seconds)', $html);

        // From an hour up it reads h:mm:ss; with no length the box is empty.
        $track->update(['duration_seconds' => 3723]);
        $this->assertStringContainsString('value="1:02:03" placeholder="0:00" disabled', $this->get("/admin/media-contents/{$track->id}/edit")->getContent());
        $this->assertStringContainsString('<input type="text" value="" placeholder="0:00" disabled', $this->get('/admin/media-contents/create/song')->getContent());

        // The hidden field is what is saved, so an update keeps the length.
        $track->update(['duration_seconds' => 1165]);
        $this->put("/admin/media-contents/{$track->id}", ['category_id' => $track->category_id, 'title' => 'Plain Track', 'media_type' => 'audio', 'status' => 'private', 'duration_seconds' => '1165'])->assertRedirect();
        $this->assertSame(1165, $track->fresh()->duration_seconds);
    }

    public function test_the_artist_is_a_card_of_its_own_under_details(): void
    {
        $this->signIn();
        $first = \Src\Catalog\Models\Artist::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $second = \Src\Catalog\Models\Artist::create(['name' => 'Bravo', 'slug' => 'bravo', 'stage_name' => 'B-Boy']);
        $track = $this->track('Plain Track', 'song', ['artist_ids' => [$second->id]]);

        // Song (new and edit) and the whole form: Category first, then a card with only the Artist: a search box and a
        // tick box for every artist (by stage name where they have one). Ambient Music leaves the Artist out (below).
        foreach (['/admin/media-contents/create/song', "/admin/media-contents/edit/song/{$track->id}", "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $afterDetails = substr($html, strpos($html, '<h4>Category</h4>'));
            $nextCard = strpos($afterDetails, 'class="sidebar-box"');

            $this->assertNotFalse($nextCard, "{$url}: a card follows Category");
            $this->assertStringNotContainsString('artist_ids[]', substr($afterDetails, 0, $nextCard), "{$url}: not inside Category");
            $card = substr($afterDetails, $nextCard);
            // The card is a caption over a dropdown (as Region and Genre have): a button that reads "Select Artist", and a
            // panel that opens on the search box, then the list.
            $this->assertMatchesRegularExpression('/^class="sidebar-box">\s*<span[^>]*>Artist<\/span>\s*<div class="tag-dropdown" data-artist-dropdown>\s*<button type="button" class="tag-dropdown-toggle"[^>]*data-artist-toggle>\s*<span data-artist-label[^>]*>Select Artist<\/span>/', $card, $url);
            $this->assertMatchesRegularExpression('/<div class="tag-dropdown-panel" data-artist-panel hidden>\s*<div class="tag-dropdown-search">\s*<input id="artist_search" type="search" placeholder="Search artist…"[^>]*data-artist-search>.*?<div class="tag-tree" style="max-height:164px" data-artist-list>/s', $card, $url);
            $this->assertMatchesRegularExpression('/<input type="checkbox" name="artist_ids\[\]" value="'.$first->id.'" >\s*<span>Alpha<\/span>/', $card, $url);
            $this->assertMatchesRegularExpression('/name="artist_ids\[\]" value="'.$second->id.'"[^>]*>\s*<span>B-Boy<\/span>/', $card, $url);
            $this->assertStringContainsString("box.querySelector('[data-artist-search]')", $html, $url);
            // Five rows show and the rest scroll: a fallback height in the markup, refined by the script from the rows.
            $this->assertStringContainsString('<div class="tag-tree" style="max-height:164px" data-artist-list>', $html, $url);
            $this->assertStringContainsString('list.style.maxHeight', $html, $url);
        }

        // Ambient Music (new and edit) leaves the Artist out: no card, no field.
        $calm = $this->track('Calm Track', 'ambient-music', ['artist_ids' => [$second->id]]);
        foreach (['/admin/media-contents/create/ambient-music', "/admin/media-contents/edit/ambient-music/{$calm->id}"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('id="artist_search"', $html, $url);
            $this->assertStringNotContainsString('name="artist_ids[]"', $html, $url);
        }

        // The Podcast form, new and edit, has no Artist card either.
        $episode = $this->track('Episode One', 'podcast');
        foreach (['/admin/media-contents/create/podcast', "/admin/media-contents/edit/podcast/{$episode->id}"] as $url) {
            $this->get($url)->assertOk()->assertDontSee('id="artist_search"', false);
        }

        // An episode added from a podcast's own page has no Artist card; an unknown podcast is the plain form again.
        $podcast = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $this->get("/admin/media-contents/create?podcast={$podcast->id}")->assertOk()->assertDontSee('id="artist_search"', false);
        $this->get('/admin/media-contents/create?podcast=9999')->assertOk()->assertSee('id="artist_search"', false);

        // The item's own artist is ticked on its edit form (and only that one).
        $edit = $this->get("/admin/media-contents/edit/song/{$track->id}")->getContent();
        $this->assertMatchesRegularExpression('/name="artist_ids\[\]" value="'.$second->id.'" checked>/', $edit);
        $this->assertDoesNotMatchRegularExpression('/name="artist_ids\[\]" value="'.$first->id.'" checked/', $edit);
    }

    public function test_the_artist_list_follows_the_region_picked_above_it(): void
    {
        $this->signIn();
        $far = \Src\Catalog\Models\Artist::create(['name' => 'Far Star', 'slug' => 'far-star', 'region' => 'international']);
        $near = \Src\Catalog\Models\Artist::create(['name' => 'Near Star', 'slug' => 'near-star', 'region' => 'local']);
        $either = \Src\Catalog\Models\Artist::create(['name' => 'No Region Yet', 'slug' => 'no-region-yet']);

        // Each row says which region its artist is from (empty while it has none), for the script to narrow the list by.
        $html = $this->get('/admin/media-contents/create/song')->assertOk()->getContent();
        foreach ([[$far, 'international'], [$near, 'local'], [$either, '']] as [$artist, $region]) {
            $this->assertMatchesRegularExpression('/<label class="tag-tree-leaf" data-artist-row data-region="'.$region.'">\s*<input type="checkbox" name="artist_ids\[\]" value="'.$artist->id.'"/', $html, $artist->name);
        }

        // The list follows the Region dropdown while it is on show (an artist with none is listed under both, a ticked one always).
        $this->assertStringContainsString("const region = document.querySelector('[data-tag-region]');", $html);
        $this->assertStringContainsString('row.dataset.region === picked', $html);
        $this->assertStringContainsString("region?.addEventListener('change', filter);", $html);
    }

    public function test_several_artists_can_be_chosen_and_each_is_kept_on_save(): void
    {
        $this->signIn();
        $a = \Src\Catalog\Models\Artist::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $b = \Src\Catalog\Models\Artist::create(['name' => 'Bravo', 'slug' => 'bravo']);
        $c = \Src\Catalog\Models\Artist::create(['name' => 'Charlie', 'slug' => 'charlie']);
        $credited = fn (MediaContent $item) => $item->fresh()->artists->pluck('id')->all();
        $song = $this->category('song');

        // New: two artists ticked.
        $this->post('/admin/media-contents', ['category_id' => $song->id, 'title' => 'Duet', 'media_type' => 'audio', 'status' => 'private', 'artist_ids' => [$a->id, $b->id]])->assertRedirect();
        $duet = MediaContent::where('title', 'Duet')->firstOrFail();
        $this->assertSame([$a->id, $b->id], $credited($duet));
        $this->assertSame('Alpha, Bravo', $duet->fresh()->artist_names);

        // Edit: a different pair, then one, then none.
        $edit = fn (array $extra) => $this->put("/admin/media-contents/{$duet->id}", ['category_id' => $song->id, 'title' => 'Duet', 'media_type' => 'audio', 'status' => 'private', ...$extra])->assertRedirect();
        $edit(['artist_ids' => [$b->id, $c->id]]);
        $this->assertSame([$b->id, $c->id], $credited($duet));
        $edit(['artist_ids' => [$c->id]]);
        $this->assertSame([$c->id], $credited($duet));
        $edit([]);
        $this->assertSame([], $credited($duet));

        // An artist that doesn't exist, or the same one twice, is refused.
        $this->post('/admin/media-contents', ['category_id' => $song->id, 'title' => 'Bad', 'media_type' => 'audio', 'status' => 'private', 'artist_ids' => [99999]])->assertSessionHasErrors('artist_ids.0');
        $this->post('/admin/media-contents', ['category_id' => $song->id, 'title' => 'Twice', 'media_type' => 'audio', 'status' => 'private', 'artist_ids' => [$a->id, $a->id]])->assertSessionHasErrors();
        $this->assertDatabaseMissing('media_contents', ['title' => 'Bad']);
    }

    public function test_news_health_economy_finance_ambient_and_podcast_leave_their_top_row_out_and_economy_finance_and_health_have_radio_buttons(): void
    {
        $this->signIn();

        // The News form lists all three trees; each one's top row is marked to give way to its own rows, and no other
        // tree (Song, Podcast, Ambient Music) is.
        $html = $this->get('/admin/media-contents/create/news')->assertOk()->getContent();
        $this->assertSame(3, preg_match_all('/<details class="tag-tree-node" open\s+data-category-id="\d+"\s+data-fold-root\s*>/', $html));
        foreach (['news', 'health', 'economyfinance'] as $slug) {
            $this->assertMatchesRegularExpression('/<details class="tag-tree-node" open\s+data-category-id="'.$this->category($slug)->id.'"\s+data-fold-root\s*>/', $html, $slug);
        }
        $this->assertStringContainsString("hasAttribute('data-fold-root')", $html);

        // The Ambient Music form leaves its tree's top row out too, so it lists only the moods.
        $ambient = $this->get('/admin/media-contents/create/ambient-music')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/<details class="tag-tree-node" open\s+data-category-id="\d+"\s+data-fold-root\s*>/', $ambient));
        $this->assertMatchesRegularExpression('/<details class="tag-tree-node" open\s+data-category-id="'.$this->category('ambient-music')->id.'"\s+data-fold-root\s*>/', $ambient);

        // So does the Podcast tree: on the Podcast form, and on a new episode opened from a podcast's own page.
        $podcastRoot = '/<details class="tag-tree-node" open\s+data-category-id="'.$this->category('podcast')->id.'"\s+data-fold-root\s*>/';
        $show = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $this->assertMatchesRegularExpression($podcastRoot, $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent());
        $this->assertMatchesRegularExpression($podcastRoot, $this->get("/admin/media-contents/create?podcast={$show->id}")->assertOk()->getContent());

        $this->assertStringContainsString('<span>Mental Health</span>', $html);
        $this->assertStringContainsString('<span>Physical Health</span>', $html);

        // Economy/Finance's two rows (International, Local) and Health's two (Mental Health, Physical Health) are radio buttons
        // — one at a time — and every other tag, News's five included, stays a tick box.
        $tag = fn (string $name, string $categorySlug) => \Src\Catalog\Models\Tag::where('name', $name)->where('category_id', $this->category($categorySlug)->id)->value('id');
        foreach (['International', 'Local'] as $name) {
            $this->assertMatchesRegularExpression('/<input type="radio" name="tags\[\]" value="'.$tag($name, 'economyfinance').'"[^>]*>\s*<span>'.$name.'<\/span>/', $html, $name);
        }
        foreach (['Mental Health', 'Physical Health'] as $name) {
            $this->assertMatchesRegularExpression('/<input type="radio" name="tags\[\]" value="'.$tag($name, 'health').'"[^>]*>\s*<span>'.$name.'<\/span>/', $html, $name);
        }
        $this->assertSame(4, substr_count($html, '<input type="radio" name="tags[]"'));
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="tags\[\]" value="'.$tag('Politic', 'news').'"/', $html);
        // Clicking the picked radio clears it again.
        $this->assertStringContainsString("[data-tag-tree] input[type=radio]", $html);
    }

    public function test_the_song_form_hides_the_category_but_still_posts_it(): void
    {
        $this->signIn();
        $song = $this->category('song');
        $ambient = $this->category('ambient-music');
        $track = $this->track('Plain Track', 'song');
        $mood = $this->track('Ambient Track', 'ambient-music');
        $podcast = $this->category('podcast');
        $episode = $this->track('Pilot Episode', 'podcast');
        $show = Program::create(['category_id' => $podcast->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);

        // Song, Ambient Music and Podcast fix their category, so the field is out of sight — but it stays in the page
        // (the scripts read it) and the hidden input still carries the category with the form. An episode opened from a
        // podcast's own page (new or edit) has its category fixed to the podcast's too.
        foreach ([
            ["/admin/media-contents/create?podcast={$show->id}", $podcast],
            ["/admin/media-contents/{$episode->id}/edit?podcast={$show->id}", $podcast],
            ['/admin/media-contents/create/song', $song],
            ["/admin/media-contents/edit/song/{$track->id}", $song],
            ['/admin/media-contents/create/ambient-music', $ambient],
            ["/admin/media-contents/edit/ambient-music/{$mood->id}", $ambient],
            ['/admin/media-contents/create/podcast', $podcast],
            ["/admin/media-contents/edit/podcast/{$episode->id}", $podcast],
        ] as [$url, $category]) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<label\s+style="display:none"\s*>Category\s*<input type="hidden" name="category_id" value="'.$category->id.'">\s*<select id="category_id"/', $html, $url);
        }

        // The News form (a real choice of three categories) and the whole form keep it on show.
        foreach (['/admin/media-contents/create/news', '/admin/media-contents/create?category=none'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<label\s+style="display:none"\s*>Category/', $html, $url);
            $this->assertMatchesRegularExpression('/<label\s*>Category/', $html, $url);
        }
    }

    public function test_the_podcast_program_is_a_dropdown_that_picks_one_under_a_search_box(): void
    {
        $this->signIn();

        // Under the select (which stays in the form and does the work; the script hides it) comes a dropdown: a button, then
        // a panel with a search box and the list the script fills in, one podcast at a time. It starts out of sight.
        $track = $this->track('Plain Track', 'song');
        foreach (['/admin/media-contents/create/podcast', "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<select id="program_id".*?<\/select>.*?<div class="tag-dropdown" data-program-dropdown style="[^"]*" hidden>\s*<button type="button" class="tag-dropdown-toggle"[^>]*data-program-toggle>.*?<div class="tag-dropdown-panel" data-program-panel hidden>\s*<div class="tag-dropdown-search">\s*<input type="search"(?![^>]*\sname=)[^>]*data-program-search>.*?<div class="tag-tree" style="max-height:164px" data-program-list><\/div>/s', $html, $url);
            $this->assertStringContainsString("box.querySelector('[data-program-search]')", $html, $url);
            $this->assertStringContainsString("input.type = 'radio';", $html, $url);
            $this->assertStringContainsString("select.dispatchEvent(new Event('change', { bubbles: true }));", $html, $url);
        }

        // A new podcast's tag list waits, greyed out, until a Podcast Program is picked: the script checks for the placeholder
        // that only a form needing a podcast has, and the dropdown carries the hint its tooltip gives.
        $podcast = $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="tag-dropdown" data-tag-dropdown data-waiting="Pick a podcast first."\s+data-always-hidden\s*>/', $podcast);
        $this->assertStringContainsString("box.hasAttribute('data-always-hidden')", $podcast);
        $this->assertStringContainsString('<option value="" disabled hidden selected>Select a podcast…</option>', $podcast);
        $this->assertStringContainsString("program.querySelector('option[value=\"\"][disabled]')", $podcast);
        $this->assertStringContainsString('toggle.disabled = waiting;', $podcast);

        // A form that leaves the podcast out has no such dropdown.
        $this->assertStringNotContainsString('<div class="tag-dropdown" data-program-dropdown', $this->get('/admin/media-contents/create/song')->assertOk()->getContent());
    }

    public function test_each_podcast_program_option_carries_its_type_tag_for_the_tag_list_to_follow(): void
    {
        $this->signIn();
        $type = \Src\Catalog\Models\Tag::where('name', 'Health')->where('category_id', $this->category('podcast')->id)->firstOrFail();
        $show = Program::create(['category_id' => $this->category('podcast')->id, 'tag_id' => $type->id, 'name' => 'Healthy Habits', 'slug' => 'healthy-habits', 'is_published' => true]);
        $plain = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'No Type Show', 'slug' => 'no-type-show', 'is_published' => true]);

        // The option holds the id of the program's type tag (empty for a program with no type); the script ticks and locks it.
        $html = $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$show->id.'" data-category-id="\d+" data-tag-id="'.$type->id.'"/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$plain->id.'" data-category-id="\d+" data-tag-id=""/', $html);
        $this->assertStringContainsString('// Tags follow the Podcast Program', $html);
        $this->assertStringContainsString("addEventListener('formdata'", $html);
    }

    public function test_the_bare_create_address_goes_to_the_song_form(): void
    {
        $this->signIn();
        $song = route('admin.media-contents.create', ['category' => 'song']);

        $this->get('/admin/media-contents/create')->assertRedirect($song);

        // Anything that names what it wants is left alone: an episode of a podcast, a category, a category in the path.
        $podcast = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $this->get("/admin/media-contents/create?podcast={$podcast->id}")->assertOk();
        $this->get('/admin/media-contents/create?category=news')->assertOk();
        $this->get('/admin/media-contents/create/news')->assertOk();
    }

    public function test_the_media_content_menu_is_hidden_but_its_pages_still_work(): void
    {
        $this->signIn();

        $html = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('admin.media-contents.index').'"', $html);
        $this->assertStringNotContainsString('Media Content', substr($html, strpos($html, 'main-nav'), strpos($html, '</nav>') - strpos($html, 'main-nav')));

        // Still there by its own address.
        $this->get('/admin/media-contents')->assertOk();
        $this->get('/admin/media-contents/create/song')->assertOk();
    }

    public function test_the_sidebar_lights_music_on_media_pages_and_programs_on_a_programs_episodes(): void
    {
        $this->signIn();
        $lit = fn (string $html, string $route) => (bool) preg_match('/<a href="'.preg_quote(route($route), '/').'" class="active">/', $html);

        $media = $this->get('/admin/media-contents/create?category=none')->assertOk()->getContent();
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
        $order = array_map(fn ($key) => strpos($html, 'name="category" value="'.$key.'"'), ['', 'song', 'audio', 'podcast', 'ambient']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        foreach (['news', 'health', 'economyfinance', 'ambient-music'] as $slug) {
            $this->assertStringNotContainsString('name="category" value="'.$slug.'"', $html);
        }
        $this->assertMatchesRegularExpression('/value="" [^>]*class="active">All</', $html);
        $this->assertMatchesRegularExpression('/value="audio" [^>]*>Audio</', $html);
        $this->assertMatchesRegularExpression('/value="podcast" [^>]*>Podcast Episode</', $html);
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
        $program = $this->get('/admin/music?category=audio')
            ->assertOk()
            ->assertSee('A News Story')
            ->assertSee('A Health Tip')
            ->assertSee('A Market Note')
            ->assertDontSee('A Song')
            ->assertDontSee('An Old Ambient Track')
            ->assertDontSee('A Podcast Episode')
            ->getContent();
        $this->assertMatchesRegularExpression('/value="audio" [^>]*class="active">Audio</', $program);

        // Podcasts: the podcast episodes only.
        $podcasts = $this->get('/admin/music?category=podcast')
            ->assertOk()
            ->assertSee('A Podcast Episode')
            ->assertDontSee('A Song')
            ->assertDontSee('A News Story')
            ->assertDontSee('An Old Ambient Track')
            ->getContent();
        $this->assertMatchesRegularExpression('/value="podcast" [^>]*class="active">Podcast Episode</', $podcasts);

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

        // Its entry in the New menu opens the Ambient Music form (and is the one marked).
        $this->assertNewMenuEntry($html, 'ambient-music', 'fa-moon-o', 'Ambient Music', true);

        // All still lists each of them once.
        $all = $this->get('/admin/music')->assertOk()->assertSee('Calm Song')->assertSee('Filed Under Ambient')->getContent();
        $this->assertSame(1, substr_count($all, 'Calm Song'));
    }

    public function test_new_ambient_music_fixes_the_category_on_ambient_music_and_hides_podcast_and_artist(): void
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

        // No Podcast field and no Artist field; the moods are ticked in the Tags box (no card).
        foreach (['id="program_id"', 'name="program_id"', 'No podcast', 'id="artist_search"', 'name="artist_ids[]"'] as $gone) {
            $this->assertStringNotContainsString($gone, $form);
        }
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

    public function test_the_list_can_be_filtered_by_category_and_status(): void
    {
        $this->signIn();
        // Status is worked out from is_published and published_at: public, scheduled (published, but later) or private.
        $past = now()->subDays(5);
        $future = now()->addDays(30);
        $day = fn ($moment) => $moment->copy()->setTimezone(config('app.admin_timezone'))->format('Y-m-d');
        $this->track('A Song', 'song', ['is_published' => true, 'published_at' => $past]);
        $this->track('A News Story', 'news');
        $this->track('A Health Talk', 'health', ['is_published' => true, 'published_at' => $future]);

        // On All the category filter lists the categories (News and Health by name: Audio is a tab, not a category), once each; each
        // row carries its category, status and local day for the script to filter by.
        $html = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="table-filters" id="article-filters"[^>]*>\s*<label class="filter-field"><span>Category<\/span>\s*<span class="filter-control"><i class="fa fa-folder-o" aria-hidden="true"><\/i>\s*<select data-filter="category">\s*<option value="">All categories<\/option>\s*<option value="Health">Health<\/option>\s*<option value="News">News<\/option>\s*<option value="Song">Song<\/option>\s*<\/select>/s', $html);
        $this->assertMatchesRegularExpression('/<label class="filter-field"><span>Status<\/span>\s*<span class="filter-control"><i class="fa fa-circle-o" aria-hidden="true"><\/i>\s*<select data-filter="status">\s*<option value="">All statuses<\/option>\s*<option value="public">Public<\/option>\s*<option value="scheduled">Scheduled<\/option>\s*<option value="private">Private<\/option>/', $html);
        $this->assertMatchesRegularExpression('/data-filter-clear title="Clear filters" aria-label="Clear filters" hidden>/', $html);
        $this->assertStringNotContainsString('type="date"', $html);
        $this->assertStringContainsString('<th>Published</th>', $html);
        $this->assertStringContainsString('<tr data-category="Song" data-status="public" data-published="'.$day($past).'" data-region="none">', $html);
        $this->assertStringContainsString('<tr data-category="News" data-status="private" data-published="" data-region="none">', $html);
        $this->assertStringContainsString('<tr data-category="Health" data-status="scheduled" data-published="'.$day($future).'" data-region="none">', $html);
        $this->assertStringContainsString('jQuery.fn.dataTable.ext.search.push(', $html);
        // One row, in the table's order: the script moves DataTables' search box (the Title column) to the front of the filter
        // strip and its "per page" to the end.
        $this->assertStringContainsString('filters.prepend(searchField);', $html);
        $this->assertStringContainsString("tools.append(wrapper.querySelector('.dt-length'));", $html);

        // On the Audio tab it lists the categories themselves.
        $audio = $this->get('/admin/music?category=audio')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select data-filter="category">\s*<option value="">All categories<\/option>\s*<option value="Health">Health<\/option>\s*<option value="News">News<\/option>\s*<\/select>/', $audio);

        // An empty tab has nothing to filter, so no bar.
        $this->assertStringNotContainsString('id="article-filters"', $this->get('/admin/music?category=podcast')->assertOk()->getContent());
    }

    public function test_the_pages_load_their_text_font_from_google_fonts(): void
    {
        $this->signIn();

        // The font stack names Inter first; the head loads it (every layout includes the same partial), so it no longer depends on
        // the computer having it installed.
        $html = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="preconnect" href="https://fonts.googleapis.com">', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">', $html);
        $this->assertStringNotContainsString('Noto+Sans+Khmer', $html);
    }

    public function test_the_list_adapts_to_every_screen_size(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $html = $this->get('/admin/music')->assertOk()->getContent();

        // Every value cell names its column, for the card layout on phones (the title and the Actions cells need no label).
        foreach (['Category', 'Region', 'Audio', 'Views', 'Favorites', 'Status', 'Published'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html, $label);
        }
        $this->assertSame(7, substr_count($html, ' data-label="'));

        // Wide and medium: columns keep their width and the table scrolls sideways inside its card; phones: rows become cards.
        $this->assertStringContainsString('#article-table { min-width: 1000px; }', $html);
        $this->assertStringContainsString('#article-table .table-actions { flex-wrap: nowrap; }', $html);
        $this->assertMatchesRegularExpression('/\.dt-layout-row\.dt-layout-table \{[^}]*overflow-x: auto/', $html);
        $this->assertStringContainsString('@media (max-width: 760px)', $html);
        $this->assertStringContainsString('content: attr(data-label)', $html);
        $this->assertStringContainsString('#article-table thead { display: none; }', $html);
        $this->assertStringContainsString('@media (max-width: 480px)', $html);
    }

    public function test_the_title_cell_ends_with_the_file_size_and_length(): void
    {
        $this->signIn();
        $artist = \Src\Catalog\Models\Artist::create(['name' => 'Alpha', 'slug' => 'alpha']);
        Storage::disk('public')->put('audio/full.opus', str_repeat('a', 2621440)); // 2.5 MB

        $this->track('Full Track', 'song', ['stream' => 'audio/full.opus', 'processing_status' => 'ready', 'duration_seconds' => 705, 'artist_ids' => [$artist->id]]);
        $this->track('Length Only', 'news', ['duration_seconds' => 3723]);
        // A link (or a file on cloud storage) has no size to name, and no length: nothing is added.
        $this->track('Link Only', 'news', ['stream' => 'https://example.com/a.mp3', 'processing_status' => 'ready']);

        $html = $this->get('/admin/music')->assertOk()->getContent();

        // Names, then the size and the length as m:s (h:mm:ss from an hour up), each part only when there is one.
        $this->assertStringContainsString('<small class="muted">Alpha · 2.5 MB · 11:45</small>', $html);
        $this->assertStringContainsString('<small class="muted">1:02:03</small>', $html);
        $this->assertSame(2, substr_count($html, '<small class="muted">'));
    }

    public function test_the_content_list_can_be_filtered_by_region_from_tags_and_artists(): void
    {
        $this->signIn();
        $song = $this->category('song');
        // A genre under Song > International, and one under Song > Local.
        $genre = function (string $region) use ($song) {
            $branch = Tag::where('category_id', $song->id)->where('name', $region)->whereNotNull('parent_id')->firstOrFail();

            return Tag::whereIn('parent_id', Tag::where('parent_id', $branch->id)->where('name', 'Genre')->pluck('id'))->firstOrFail();
        };
        $far = \Src\Catalog\Models\Artist::create(['name' => 'Far Star', 'slug' => 'far-star', 'region' => 'international']);
        $near = \Src\Catalog\Models\Artist::create(['name' => 'Near Star', 'slug' => 'near-star', 'region' => 'local']);

        $this->track('Tagged International', 'song')->tags()->attach($genre('International')->id);
        $this->track('Artist Is Local', 'song', ['artist_ids' => [$near->id]]);
        $both = $this->track('Tagged Local By An International Artist', 'song', ['artist_ids' => [$far->id]]);
        $both->tags()->attach($genre('Local')->id);
        $this->track('Nothing To Say', 'news');

        $html = $this->get('/admin/music')->assertOk()->getContent();

        // The strip offers the Region: International, Local, or not set.
        $this->assertMatchesRegularExpression('/<label class="filter-field"><span>Region<\/span>\s*<span class="filter-control"><i class="fa fa-globe" aria-hidden="true"><\/i>\s*<select data-filter="region">\s*<option value="">All regions<\/option>\s*<option value="international">International<\/option>\s*<option value="local">Local<\/option>\s*<option value="none">Not set<\/option>\s*<\/select>/', $html);

        // Each row carries one region: from the item's tags (however deep under the branch), else from its artists, else none.
        // The item's own tag wins over its artist, so a Local tag on a song by an International artist is Local.
        $rows = fn (string $region) => substr_count($html, 'data-region="'.$region.'">');
        $this->assertSame(1, $rows('international'), 'tagged International');
        $this->assertSame(2, $rows('local'), 'an artist that is Local, and a Local tag over an International artist');
        $this->assertSame(0, substr_count($html, 'data-region="international|local"'), 'never both');
        $this->assertSame(1, $rows('none'), 'nothing');

        // The Region column shows the same value as one badge (International or Local), and a dash when there is none.
        $this->assertStringContainsString('<th>Region</th>', $html);
        $this->assertStringNotContainsString('<th>File size</th>', $html);
        $this->assertSame(1, substr_count($html, '<span class="badge slate">International</span>'));
        $this->assertSame(2, substr_count($html, '<span class="badge slate">Local</span>'));
    }

    public function test_an_empty_tab_says_so_and_is_still_a_datatable(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');

        $html = $this->get('/admin/music?category=audio')
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

    public function test_all_shows_each_rows_own_category_not_a_group_name(): void
    {
        $this->signIn();
        $this->track('A Song', 'song');
        $this->track('An Old Ambient Track', 'ambient-music');
        $this->track('A News Story', 'news');
        $this->track('A Health Tip', 'health');
        $this->track('A Market Note', 'economyfinance');
        $this->track('A Podcast Episode', 'podcast');
        $cell = fn (string $html, string $title) => preg_match('/'.preg_quote($title, '/').'<\/b>.*?<td class="muted" data-label="Category">([^<]*)<\/td>/s', $html, $m) ? trim($m[1]) : null;

        // All: every row by its own category name — never "Audio" (a tab) or "Podcast Episode" (the Podcast category's tab label).
        $all = $this->get('/admin/music')->assertOk()->getContent();
        $this->assertSame('Song', $cell($all, 'A Song'));
        $this->assertSame('Ambient Music', $cell($all, 'An Old Ambient Track'));
        $this->assertSame('News', $cell($all, 'A News Story'));
        $this->assertSame('Health', $cell($all, 'A Health Tip'));
        $this->assertSame('Economy/Finance', $cell($all, 'A Market Note'));
        $this->assertSame('Podcast', $cell($all, 'A Podcast Episode'));
        $this->assertStringNotContainsString('data-category="Audio"', $all);
        $this->assertStringNotContainsString('data-category="Podcast Episode"', $all);

        // On the Program tab the category says which of the three it is.
        $program = $this->get('/admin/music?category=audio')->assertOk()->getContent();
        $this->assertSame('News', $cell($program, 'A News Story'));
        $this->assertSame('Health', $cell($program, 'A Health Tip'));
        $this->assertSame('Economy/Finance', $cell($program, 'A Market Note'));
    }

    public function test_the_audio_column_says_no_audio_for_an_item_with_no_file(): void
    {
        $this->signIn();
        $this->track('A News Draft', 'news');
        $this->track('A News Story', 'news', ['stream' => 'https://cdn.example.com/story.opus']);
        $cell = fn (string $html, string $title) => preg_match('/'.preg_quote($title, '/').'<\/b>.*?<td data-label="Audio">\s*<span class="badge ([a-z]+)">([^<]*)<\/span>/s', $html, $m) ? [$m[1], $m[2]] : null;

        $html = $this->get('/admin/music')->assertOk()->getContent();

        $this->assertSame(['slate', 'No audio'], $cell($html, 'A News Draft'));
        $this->assertSame(['green', 'Ready'], $cell($html, 'A News Story'));
    }

    /** One entry of the "New" menu: a link to the form it starts, with its icon and name (marked while its tab is on show). */
    private function assertNewMenuEntry(string $html, string $slug, string $icon, string $name, bool $active): void
    {
        $link = preg_quote(e(route('admin.media-contents.create', ['category' => $slug])), '/');
        $class = $active ? 'class="active"' : 'class=""';
        $this->assertMatchesRegularExpression('/<a href="'.$link.'" '.$class.'><i class="fa '.$icon.'" aria-hidden="true"><\/i>'.preg_quote($name, '/').'<\/a>/', $html, "{$name} in the New menu");
    }

    public function test_new_song_opens_the_form_on_the_song_category(): void
    {
        $this->signIn();
        $song = $this->category('song');

        $html = $this->get('/admin/music')->assertOk()->getContent();
        // "New" is a drop-down: a button that opens a menu with an icon and a link for each tab, Ambient Music included.
        $this->assertStringContainsString('<button type="button" class="primary-button" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>New <i class="fa fa-plus"', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('Select category', $html);
        $this->assertNewMenuEntry($html, 'song', 'fa-music', 'Song', false);

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
        foreach (['id="program_id"', 'name="program_id"', 'No podcast', 'name="artist_ids[]"', 'id="artist_search"'] as $gone) {
            $this->assertStringNotContainsString($gone, $form);
        }
        $this->assertStringContainsString('id="program_id"', $this->get('/admin/media-contents/create/podcast')->assertOk()->getContent());
    }

    public function test_the_podcast_field_has_a_red_new_button_that_opens_the_new_podcast_page(): void
    {
        $this->signIn();
        $create = e(route('admin.programs.create'));

        // New Podcast (and the plain media form): a compact red button on the Podcast label's row, in a new tab.
        foreach (['/admin/media-contents/create/podcast', '/admin/media-contents/create?category=none'] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/<label>\s*<span[^>]*>\s*Podcast Program\s*<a href="'.preg_quote($create, '/').'" target="_blank" rel="noopener" class="primary-button compact"[^>]*>New<\/a>\s*<\/span>\s*<select id="program_id"/s',
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
        foreach (['/admin/media-contents/create?category=none', "/admin/media-contents/{$track->id}/edit", "/admin/media-contents/create?podcast={$podcast->id}"] as $url) {
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
            'podcast' => 'New Podcast Episode Media Content',
            'ambient-music' => 'New Ambient Music Media Content',
        ] as $slug => $expected) {
            [$crumb, $bar, $tab] = $titles($this->get("/admin/media-contents/create/{$slug}")->assertOk()->getContent());
            $this->assertSame($expected, $crumb, $slug);
            $this->assertSame($expected, $bar, $slug);
            $this->assertStringStartsWith($expected.' · ', $tab, $slug);
        }

        // No category yet: as before.
        $plain = $this->get('/admin/media-contents/create?category=none')->assertOk()->getContent();
        $this->assertSame(['New Media Content', 'New media content'], array_slice($titles($plain), 0, 2));

        // An episode added from a podcast's own page, and editing, are untouched.
        $program = Program::create(['category_id' => $this->category('podcast')->id, 'name' => 'Morning Brief', 'slug' => 'morning-brief', 'is_published' => true]);
        $episode = $titles($this->get("/admin/media-contents/create?podcast={$program->id}")->assertOk()->getContent());
        $this->assertSame(['New Podcast Episode Content', 'New episode'], array_slice($episode, 0, 2));
        $this->assertStringStartsWith('New Podcast Episode Content · ', $episode[2]);
        $track = $this->track('Plain Track', 'song');
        // Editing from the Article page names the category like its New button; the plain edit address doesn't.
        // The top bar keeps the item's own title either way.
        $edit = $titles($this->get("/admin/media-contents/edit/song/{$track->id}")->assertOk()->getContent());
        $this->assertSame(['Edit Song Media Content', 'Plain Track'], array_slice($edit, 0, 2));
        $this->assertStringStartsWith('Edit Song Media Content · ', $edit[2]);
        $plainEdit = $titles($this->get("/admin/media-contents/{$track->id}/edit")->assertOk()->getContent());
        $this->assertSame(['Edit Media Content', 'Plain Track'], array_slice($plainEdit, 0, 2));

        // Podcast reads "Podcast Episode" in these titles (new and edit); the Category select keeps saying "Podcast", and its
        // option carries the title name for the script that renames the title when the category is changed.
        $ep = $this->track('Pilot Episode', 'podcast');
        $podEdit = $titles($this->get("/admin/media-contents/edit/podcast/{$ep->id}")->assertOk()->getContent());
        $this->assertSame(['Edit Podcast Episode Media Content', 'Pilot Episode'], array_slice($podEdit, 0, 2));
        $this->assertStringStartsWith('Edit Podcast Episode Media Content · ', $podEdit[2]);
        // Opened from a podcast's own page, an episode's edit form reads "Edit Podcast Episode Content", its bar keeping its own title.
        $fromShow = $titles($this->get("/admin/media-contents/{$ep->id}/edit?podcast={$program->id}")->assertOk()->getContent());
        $this->assertSame(['Edit Podcast Episode Content', 'Pilot Episode'], array_slice($fromShow, 0, 2));
        $this->assertStringContainsString('data-title-name="Podcast Episode"', $plain);
        $this->assertMatchesRegularExpression('/data-title-name="Podcast Episode"[^>]*>Podcast<\/option>/', $plain);
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
        foreach (['audio' => 'news', 'podcast' => 'podcast', 'ambient' => 'ambient-music'] as $tab => $slug) {
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
        $this->assertStringContainsString('id="artist_search"', $form);
        $this->assertStringContainsString('Edit Song Media Content', $form);

        // Program (News, Health, Economy/Finance): just those three to pick from, no Podcast, no Artist — like New Program.
        foreach (['news' => 'News', 'health' => 'Health', 'economyfinance' => 'Economy/Finance'] as $slug => $name) {
            $item = $this->track("A {$name} Item", $slug);
            $form = $this->get("/admin/media-contents/edit/{$slug}/{$item->id}")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $form, $slug);
            $this->assertEqualsCanonicalizing(['Select a category…', 'News', 'Health', 'Economy/Finance'], $categoryOptions($form), $slug);
            $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$item->category_id.'"[^>]* selected>'.preg_quote($name, '/').'</s', $form, $slug);
            foreach (['id="program_id"', 'name="program_id"', 'name="artist_ids[]"', 'id="artist_search"', 'No podcast'] as $gone) {
                $this->assertStringNotContainsString($gone, $form, "{$slug} {$gone}");
            }
            $this->assertStringContainsString("Edit {$name} Media Content", $form, $slug);
        }

        // Ambient Music: fixed on it, no Podcast and no Artist — like New Ambient Music.
        $ambient = $this->track('An Old Ambient Track', 'ambient-music');
        $form = $this->get("/admin/media-contents/edit/ambient-music/{$ambient->id}")->assertOk()->getContent();
        $this->assertTrue($locked($form, $ambient->category_id));
        $this->assertStringNotContainsString('id="program_id"', $form);
        $this->assertStringNotContainsString('id="artist_search"', $form);
        $this->assertStringNotContainsString('name="artist_ids[]"', $form);
        $this->assertStringContainsString('Edit Ambient Music Media Content', $form);

        // Podcast: fixed on Podcast, the Podcast field has no "No podcast", and its New button is there — like New
        // Podcast. The item's own podcast is the one picked.
        $episode = $this->track('Episode One', 'podcast', ['program_id' => $program->id]);
        $form = $this->get("/admin/media-contents/edit/podcast/{$episode->id}")->assertOk()->getContent();
        $this->assertTrue($locked($form, $episode->category_id));
        $this->assertStringNotContainsString('No podcast', $form);
        $this->assertMatchesRegularExpression('/<option value="'.$program->id.'"[^>]* selected>Morning Brief<\/option>/', $form);
        $this->assertStringContainsString(e(route('admin.programs.create')), $form);
        $this->assertStringContainsString('Edit Podcast Episode Media Content', $form);
        // One filed under Podcast with no podcast yet shows the placeholder.
        $orphan = $this->track('Orphan Episode', 'podcast');
        $this->assertMatchesRegularExpression('/<option value="" disabled hidden selected>Select a podcast…<\/option>/', $this->get("/admin/media-contents/edit/podcast/{$orphan->id}")->getContent());

        // The plain edit address is still the whole form.
        $whole = $this->get("/admin/media-contents/{$song->id}/edit")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id"\s+name="category_id" required\s*>/', $whole);
        $this->assertStringContainsString('id="program_id" name="program_id"', $whole);
        $this->assertStringContainsString('id="artist_search"', $whole);
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

        $artistsOf = fn (MediaContent $item) => $item->fresh()->artists->pluck('id')->all();

        // A news item with a podcast and an artist: its form shows neither, so a save leaves both.
        $news = $this->track('A News Story', 'news', ['program_id' => $program->id, 'artist_ids' => [$artist->id]]);
        $this->put("/admin/media-contents/{$news->id}", $payload($news, ['title' => 'A News Story, edited']))->assertRedirect(route('admin.music.index'));
        $news->refresh();
        $this->assertSame('A News Story, edited', $news->title);
        $this->assertSame($program->id, $news->program_id);
        $this->assertSame([$artist->id], $artistsOf($news));

        // A song with a podcast: Podcast is off its form (kept), Artist is on it (what is posted wins).
        $song = $this->track('A Song', 'song', ['program_id' => $program->id, 'artist_ids' => [$artist->id]]);
        $this->put("/admin/media-contents/{$song->id}", $payload($song, ['artist_ids' => [$other->id]]))->assertRedirect(route('admin.music.index'));
        $this->assertSame($program->id, $song->fresh()->program_id);
        $this->assertSame([$other->id], $artistsOf($song));
        $this->put("/admin/media-contents/{$song->id}", $payload($song))->assertRedirect(route('admin.music.index'));
        $this->assertSame($program->id, $song->fresh()->program_id);
        $this->assertSame([], $artistsOf($song));

        // A podcast episode's form shows the Podcast but not the Artist, so a save keeps the artist; and the plain edit form clears as before.
        $episode = $this->track('Episode One', 'podcast', ['program_id' => $program->id, 'artist_ids' => [$artist->id]]);
        $this->put("/admin/media-contents/{$episode->id}", $payload($episode, ['program_id' => $program->id]))->assertRedirect(route('admin.music.index'));
        $this->assertSame($program->id, $episode->fresh()->program_id);
        $this->assertSame([$artist->id], $artistsOf($episode));
        // An Ambient Music item's form leaves the Artist out too, so a save keeps the artist the item has.
        $calm = $this->track('Calm Track', 'ambient-music', ['artist_ids' => [$artist->id]]);
        $this->put("/admin/media-contents/{$calm->id}", $payload($calm))->assertRedirect(route('admin.music.index'));
        $this->assertSame([$artist->id], $artistsOf($calm));
        $plain = $this->track('Plain News', 'news', ['program_id' => $program->id, 'artist_ids' => [$artist->id]]);
        $this->put("/admin/media-contents/{$plain->id}", $payload($plain, ['from' => null]))->assertRedirect(route('admin.media-contents.index'));
        $this->assertNull($plain->fresh()->program_id);
        $this->assertSame([], $artistsOf($plain));
    }

    public function test_the_description_card_is_set_to_grow_to_the_height_of_the_sidebar(): void
    {
        $this->signIn();
        $track = $this->track('A Song', 'song');

        // The same card on the new form and the edit form: the main column is as tall as the sidebar, the card takes
        // the room left, the editor fills it. (Checked in a real browser: its bottom edge meets the Details card's.)
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
        $this->assertStringContainsString('New Podcast Episode Media Content', $form);

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
            $this->assertStringContainsString('id="artist_search"', $form, $url);
            foreach (['Song', 'News', 'Health', 'Economy/Finance', 'Podcast'] as $name) {
                $this->assertStringContainsString(">{$name}</option>", $form, "{$url} offers {$name}");
            }
        }
    }

    public function test_the_tag_box_has_a_region_select_for_international_and_local(): void
    {
        $this->signIn();

        // At the top of the tags part of the Details card, under its "Region" caption (there is no search box), on the new form and the edit form
        // alike; a script narrows the tree to the pick. Just the two regions: there is no "all" option.
        $track = $this->track('Plain Track', 'song');
        foreach (['/admin/media-contents/create/song', "/admin/media-contents/{$track->id}/edit"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/<span[^>]*>Region<\/span>\s*<label class="tag-region"[^>]*>\s*<select data-tag-region[^>]*>\s*<option value="international">International<\/option>\s*<option value="local">Local<\/option>\s*<\/select>/s',
                $html,
            );
            // The tags are in the Details card, under a "Region" caption after its Category, and are no card of their own.
            $this->assertStringNotContainsString('<h4>Tags</h4>', $html);
            $afterDetails = substr($html, strpos($html, '<h4>Category</h4>'));
            $detailsCard =substr($afterDetails, 0, strpos($afterDetails, 'class="sidebar-box"'));
            $this->assertMatchesRegularExpression('/<select id="category_id".*<span[^>]*>Region<\/span>.*data-tag-tree/s', $detailsCard);
            // A title between the region dropdown and the tree names what the list starts with (a script fills it in).
            $this->assertMatchesRegularExpression('/<\/select>\s*<\/label>\s*<span data-tag-tree-title style="display:none;[^"]*"><\/span>\s*<div class="tag-dropdown" data-tag-dropdown[^>]*>.*?<div class="tag-tree" data-tag-tree>/s', $html);
            $this->assertStringContainsString("document.querySelector('[data-tag-tree-title]')", $html);
            // The caption goes with the region dropdown; the script leaves it out for a tree with no regions (News, Health, Economy/Finance).
            $this->assertStringContainsString('<span data-tag-caption style="display:block;font-size:11px;font-weight:700;margin:0 0 6px">Region</span>', $html);
            $this->assertStringContainsString("document.querySelector('[data-tag-caption]')", $html);
            $this->assertStringNotContainsString('All regions', $html);
            // The dropdown's panel opens on a search box that narrows the list (a script does it; it is not a field of the form).
            $this->assertMatchesRegularExpression('/<div class="tag-dropdown-panel"[^>]*>\s*<div class="tag-dropdown-search">\s*<input type="search"(?![^>]*\sname=)[^>]*data-tag-search>.*?<div class="tag-tree" data-tag-tree>/s', $html);
            $this->assertStringContainsString("box.querySelector('[data-tag-search]')", $html);
            $this->assertStringContainsString("document.querySelector('[data-tag-region]')", $html);
            // Picking the other region unticks the tags under the one that goes away, so the item can really move to it.
            $this->assertStringContainsString(".filter(node => nameOf(node) !== picked())", $html);
            $this->assertStringContainsString("input.checked = false", $html);
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

    public function test_the_new_menu_lists_every_tab_and_marks_the_one_on_show(): void
    {
        $this->signIn();

        // The same four entries on every tab, each with its icon: Song starts a Song, Audio starts News (the form's
        // category list picks Health or Economy/Finance), Podcast Episode a Podcast, Ambient Music an Ambient Music item.
        // The tab on show is marked; All marks none.
        $entries = [
            'song' => ['song', 'fa-music', 'Song'],
            'audio' => ['news', 'fa-headphones', 'Audio'],
            'podcast' => ['podcast', 'fa-podcast', 'Podcast Episode'],
            'ambient' => ['ambient-music', 'fa-moon-o', 'Ambient Music'],
        ];
        $pages = ['/admin/music' => null];
        foreach (array_keys($entries) as $tab) {
            $pages["/admin/music?category={$tab}"] = $tab;
        }
        foreach ($pages as $url => $current) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($entries as $key => [$slug, $icon, $name]) {
                $this->assertNewMenuEntry($html, $slug, $icon, $name, $current === $key);
            }
        }

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
