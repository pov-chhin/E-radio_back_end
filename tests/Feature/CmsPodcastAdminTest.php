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

class CmsPodcastAdminTest extends TestCase
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

    private function category(): Category
    {
        return Category::where('slug', 'podcast')->firstOrFail();
    }

    /** The podcast types: the tags directly under the top-level Podcast tag, by slug. */
    private function types(): \Illuminate\Support\Collection
    {
        $root = Tag::where('slug', 'podcast')->whereNull('parent_id')->firstOrFail();

        return Tag::where('parent_id', $root->id)->orderBy('id')->get()->keyBy('slug');
    }

    private function podcast(string $name = 'Morning Brief', ?Category $category = null, ?string $type = null): Program
    {
        return Program::create([
            'category_id' => ($category ?? $this->category())->id,
            'tag_id' => $type ? $this->types()[$type]->id : null,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_published' => true,
        ]);
    }

    private function episode(Program $podcast, string $title, array $attributes = []): MediaContent
    {
        return MediaContent::create([
            'category_id' => $podcast->category_id,
            'program_id' => $podcast->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            ...$attributes,
        ]);
    }

    public function test_the_sidebar_and_list_call_them_podcasts(): void
    {
        $this->signIn();

        $this->get('/admin/podcasts')
            ->assertOk()
            ->assertSee('Podcasts')
            ->assertSee('New <i class="fa fa-plus"', false)
            ->assertDontSee('Programs');
    }

    public function test_the_list_has_a_tab_per_podcast_type_and_filters_by_the_picked_one(): void
    {
        $this->signIn();
        $this->podcast('Healthy Habits', type: 'health');
        $this->podcast('Morning Brief');

        $html = $this->get('/admin/podcasts')->assertOk()->assertSee('Healthy Habits')->assertSee('Morning Brief')->getContent();
        // The tabs are the tags under Podcast — not the categories.
        foreach (['health', 'economyfinance', 'education', 'politic', 'civilization'] as $slug) {
            $this->assertStringContainsString('name="type" value="'.$slug.'"', $html);
        }
        $this->assertStringNotContainsString('name="type" value="news"', $html);
        $this->assertStringNotContainsString('name="type" value="song"', $html);
        $this->assertStringNotContainsString('name="category"', $html);
        // "All" is the lit tab until one is picked.
        $this->assertMatchesRegularExpression('/value="" [^>]*class="active">All</', $html);

        $picked = $this->get('/admin/podcasts?type=health')
            ->assertOk()
            ->assertSee('Healthy Habits')
            ->assertDontSee('Morning Brief');
        $this->assertMatchesRegularExpression('/value="health" [^>]*class="active">Health</', $picked->getContent());
    }

    public function test_an_unknown_type_tab_falls_back_to_all_and_an_empty_one_says_so(): void
    {
        $this->signIn();
        $this->podcast('Morning Brief');

        $this->get('/admin/podcasts?type=nope')->assertOk()->assertSee('Morning Brief');
        // The old category links mean nothing now.
        $this->get('/admin/podcasts?category=health')->assertOk()->assertSee('Morning Brief');
        $this->get('/admin/podcasts?type=education')
            ->assertOk()
            ->assertDontSee('Morning Brief')
            ->assertSee('No podcasts in Education yet.');
    }

    public function test_new_is_a_plain_link_to_the_create_page_and_the_form_starts_on_the_open_type(): void
    {
        $this->signIn();
        $types = $this->types();

        // No drop-down of types: one link straight to the create page (the type is picked on the form).
        $html = $this->get('/admin/podcasts')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="'.route('admin.programs.create').'" class="primary-button">New ', $html);
        foreach (['<details', 'program-new-menu', 'role="menuitem"', 'Select podcast type', 'Select a podcast type for the new podcast'] as $menu) {
            $this->assertStringNotContainsString($menu, $html);
        }
        foreach ($types->keys() as $slug) {
            $this->assertStringNotContainsString('href="'.route('admin.programs.create', ['type' => $slug]).'"', $html);
        }

        // On a type's tab the link carries that type, so the form opens on it.
        $health = $this->get('/admin/podcasts?type=health')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="'.e(route('admin.programs.create', ['type' => 'health'])).'" class="primary-button">New ', $health);
        $this->assertStringNotContainsString('<details', $health);

        $select = fn (string $html) => substr($html, $start = strpos($html, '<select id="tag_id"'), strpos($html, '</select>', $start) - $start);

        $picked = $select($this->get('/admin/podcasts/create?type=education')->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/<option value="'.$types['education']->id.'" selected>Education</', $picked);

        // Unknown picks and no pick leave the placeholder showing.
        foreach (['/admin/podcasts/create?type=nope', '/admin/podcasts/create'] as $url) {
            $this->assertStringNotContainsString(' selected>', $select($this->get($url)->getContent()));
        }
    }

    public function test_the_podcast_form_has_a_big_title_a_slug_under_it_and_its_settings_in_a_sidebar(): void
    {
        $this->signIn();
        $podcast = $this->podcast('Morning Brief');

        foreach (['/admin/podcasts/create', "/admin/podcasts/{$podcast->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();

            // The title is the field `name`, called Title: a big input like the media editor's, with no "Name" label.
            $this->assertMatchesRegularExpression('/<input id="name" name="name" type="text" class="editor-title-input" placeholder="Podcast title" aria-label="Title"/', $form, $url);
            $this->assertStringNotContainsString('<label>Name', $form, $url);
            // The slug sits right under it, in the media editor's slug row.
            $this->assertMatchesRegularExpression('/id="name".*?<label class="editor-slug-row">Slug\s*<input id="slug" name="slug" type="text" placeholder="Made from the title as you type"/s', $form, $url);
            // The description is a card; Published, the type and the cover are in the sidebar.
            $this->assertMatchesRegularExpression('/<div class="editor-main">.*?<h4>Description<\/h4>\s*<div class="rich-editor" data-rich-text><\/div>\s*<textarea id="description" name="description" data-rich-text-source hidden>/s', $form, $url);
            $sidebar = substr($form, strpos($form, '<aside class="editor-sidebar">'));
            $this->assertStringContainsString('name="is_published"', $sidebar, $url);
            $this->assertMatchesRegularExpression('/<label>Podcast type\s*<select id="tag_id"/', $sidebar, $url);
            $this->assertStringContainsString('name="cover_image"', $sidebar, $url);
            // The sidebar's order, like the media editor's: the picture first, then Status, then Details.
            $cards = [strpos($sidebar, '<h4>Cover image</h4>'), strpos($sidebar, '<h4>Status</h4>'), strpos($sidebar, '<h4>Details</h4>')];
            $this->assertNotContains(false, $cards, $url);
            $sorted = $cards;
            sort($sorted);
            $this->assertSame($sorted, $cards, $url);
            $this->assertStringNotContainsString('class="panel form-card"', $form, $url);
        }
        $this->get('/admin/podcasts/create')->assertSee('Create podcast');
        $this->get("/admin/podcasts/{$podcast->id}/edit")->assertSee('Save changes');
    }

    public function test_the_cover_card_has_a_preview_picture_for_the_current_cover_and_the_one_just_chosen(): void
    {
        $this->signIn();
        $plain = $this->podcast('No Cover Yet');
        $covered = $this->podcast('With Cover');
        $covered->update(['cover_image' => 'programs/covers/with-cover.webp', 'cover_image_disk' => null]);

        // A new podcast, or one with no cover: the picture is there but hidden, ready for what gets chosen.
        foreach (['/admin/podcasts/create', "/admin/podcasts/{$plain->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<img src="" alt="Cover image preview" data-cover-preview style="[^"]*"\s+hidden\s*>/', $form, $url);
            $this->assertStringNotContainsString('name="remove_cover_image"', $form, $url);
        }

        // One with a cover: the picture shows it, with the checkbox to remove it.
        $form = $this->get("/admin/podcasts/{$covered->id}/edit")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<img src="[^"]*programs\/covers\/with-cover\.webp" alt="Cover image preview" data-cover-preview style="[^"]*"\s*>/', $form, 'a cover that exists is not hidden');
        $this->assertStringContainsString('name="remove_cover_image"', $form);

        // The preview is above the file / address fields, inside the Cover image card, and the page's script drives it.
        $card = substr($form, $start = strpos($form, '<h4>Cover image</h4>'), strpos($form, '</aside>', $start) - $start);
        $this->assertLessThan(strpos($card, 'name="cover_image"'), strpos($card, 'data-cover-preview'));
        $this->assertStringContainsString("document.querySelector('[data-cover-preview]')", $form);
        $this->assertStringContainsString("file.addEventListener('change', refresh);", $form);
    }

    public function test_the_description_uses_the_rich_text_editor_like_media_content(): void
    {
        $this->signIn();
        $podcast = $this->podcast('Morning Brief');
        $media = $this->get('/admin/media-contents/create')->assertOk()->getContent();

        foreach (['/admin/podcasts/create', "/admin/podcasts/{$podcast->id}/edit"] as $url) {
            $form = $this->get($url)->assertOk()->getContent();

            // The same two pieces the media form gives its editor: the container the page's script turns into the
            // editor, and the hidden textarea that carries its HTML back with the form.
            $this->assertStringContainsString('<div class="editor-block description-grow">', $form, $url);
            $this->assertStringContainsString('<div class="rich-editor" data-rich-text></div>', $form);
            $this->assertStringContainsString('<div class="rich-editor" data-rich-text></div>', $media);
            $this->assertMatchesRegularExpression('/<textarea id="description" name="description" data-rich-text-source hidden>/', $form, $url);
            // No plain box any more.
            $this->assertStringNotContainsString('podcast-description', $form, $url);
            // The card grows to the bottom of the sidebar, as the media editor's does.
            $this->assertStringContainsString('.editor-layout > .editor-main { align-self: stretch; }', $form, $url);
            $this->assertStringContainsString('.editor-block.description-grow { flex: 1 1 auto; display: flex; flex-direction: column; }', $form, $url);
            $this->assertStringContainsString('.description-grow .rich-editor .ql-editor { flex: 1 1 auto; height: auto; }', $form, $url);
        }
    }

    public function test_a_description_is_given_to_the_editor_as_html(): void
    {
        $this->signIn();
        $source = fn (Program $podcast) => preg_match('/<textarea id="description"[^>]*>(.*?)<\/textarea>/s', $this->get("/admin/podcasts/{$podcast->id}/edit")->getContent(), $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;

        // Written before the editor: plain text, whose lines are kept as paragraphs.
        $plain = $this->podcast('Plain Show');
        $plain->update(['description' => "First line.
Second line, with & ampersand and \"quotes\"."]);
        $this->assertSame('<p>First line.</p><p>Second line, with &amp; ampersand and &quot;quotes&quot;.</p>', $source($plain));

        // Text that holds something tag-shaped counts as HTML and goes in as it is (the editor reads it as markup).
        $tagged = $this->podcast('Tagged Show');
        $tagged->update(['description' => 'Use <em>this</em> word']);
        $this->assertSame('Use <em>this</em> word', $source($tagged));

        // Already HTML from the editor: given back as it is.
        $rich = $this->podcast('Rich Show');
        $rich->update(['description' => '<p>Hello <strong>world</strong></p><ul><li>One</li></ul>']);
        $this->assertSame('<p>Hello <strong>world</strong></p><ul><li>One</li></ul>', $source($rich));

        // None: nothing in the box.
        $none = $this->podcast('Empty Show');
        $none->update(['description' => null]);
        $this->assertSame('', $source($none));

        // The page itself never carries a description as live markup — only escaped, inside the hidden box.
        $page = $this->get("/admin/podcasts/{$rich->id}/edit")->getContent();
        $this->assertStringNotContainsString('<strong>world</strong>', $page);
        $this->assertStringContainsString('&lt;strong&gt;world&lt;/strong&gt;', $page);
    }

    public function test_a_description_from_the_editor_is_saved_as_the_html_it_sends(): void
    {
        $this->signIn();
        $html = '<p>About the <strong>show</strong></p><ol><li>One</li><li>Two</li></ol><blockquote>Quote</blockquote>';

        $this->post('/admin/podcasts', ['tag_id' => $this->types()['health']->id, 'name' => 'Rich Show', 'slug' => '', 'description' => $html])->assertRedirect();
        $program = Program::where('slug', 'rich-show')->firstOrFail();
        $this->assertSame($html, $program->description);

        $update = '<p>Changed</p>';
        $this->put("/admin/podcasts/{$program->id}", ['name' => 'Rich Show', 'slug' => 'rich-show', 'description' => $update])->assertRedirect();
        $this->assertSame($update, $program->fresh()->description);

        // An emptied editor sends nothing, and the description is cleared.
        $this->put("/admin/podcasts/{$program->id}", ['name' => 'Rich Show', 'slug' => 'rich-show', 'description' => ''])->assertRedirect();
        $this->assertNull($program->fresh()->description);
    }

    public function test_the_save_button_is_in_a_bar_at_the_top_of_the_form_and_only_there(): void
    {
        $this->signIn();
        $podcast = $this->podcast('Morning Brief');

        foreach (['/admin/podcasts/create' => ['New Podcast', 'Create podcast'], "/admin/podcasts/{$podcast->id}/edit" => ['Morning Brief', 'Save changes']] as $url => [$title, $button]) {
            $html = $this->get($url)->assertOk()->getContent();
            $form = substr($html, $start = strpos($html, 'class="editor-form"'), strpos($html, '</form>', $start) - $start);

            // The bar: back to the list, what the Published switch says, the title, and the button on the right —
            // and it comes before the title field and everything else in the form.
            $this->assertMatchesRegularExpression(
                '/<div class="editor-topbar">\s*<a href="'.preg_quote(e(route('admin.programs.index')), '/').'" class="icon-button" aria-label="Back to podcasts">.*?<strong class="editor-topbar-title">'.preg_quote($title, '/').'<\/strong>\s*<div class="editor-topbar-actions">\s*<button type="submit" class="primary-button">'.preg_quote($button, '/').'<\/button>/s',
                $form,
                $url,
            );
            $this->assertLessThan(strpos($form, 'id="name"'), strpos($form, $button), $url);

            // One submit button in the form, none left under the description.
            $this->assertSame(1, substr_count($form, 'type="submit"'), $url);
            $this->assertSame(1, substr_count($form, $button), $url);
            $this->assertStringNotContainsString('podcast-submit', $form, $url);
        }
    }

    public function test_the_bar_badge_says_published_or_draft_and_follows_the_switch(): void
    {
        $this->signIn();
        // (The helper makes published podcasts.)
        $draft = $this->podcast('Quiet Draft');
        $draft->update(['is_published' => false]);
        $live = $this->podcast('Live Show');

        $badge = fn (string $html) => preg_match('/<span class="badge (\w+)" data-published-badge>([^<]*)<\/span>/', $html, $m) ? [$m[1], $m[2]] : null;

        $this->assertSame(['slate', 'Draft'], $badge($this->get('/admin/podcasts/create')->getContent()));
        $this->assertSame(['slate', 'Draft'], $badge($this->get("/admin/podcasts/{$draft->id}/edit")->getContent()));
        $this->assertSame(['green', 'Published'], $badge($this->get("/admin/podcasts/{$live->id}/edit")->getContent()));

        // The switch carries the hook the page's script uses to flip the badge.
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="is_published" value="1" data-published-switch checked>/', $this->get("/admin/podcasts/{$live->id}/edit")->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-published-switch checked/', $this->get("/admin/podcasts/{$draft->id}/edit")->getContent());
    }

    public function test_the_slug_follows_the_title_only_while_it_is_empty(): void
    {
        $this->signIn();
        $existing = $this->podcast('Morning Brief');

        // The page's script: a new podcast's slug follows its title; one that has a slug keeps it.
        $script = fn (string $url) => $this->get($url)->assertOk()->getContent();
        foreach (['/admin/podcasts/create', "/admin/podcasts/{$existing->id}/edit"] as $url) {
            $html = $script($url);
            $this->assertStringContainsString("let following = slug.value === '';", $html, $url);
            $this->assertStringContainsString("title.addEventListener('input', () => { if (following) slug.value = slugify(title.value); });", $html, $url);
            $this->assertStringContainsString("slug.addEventListener('input', () => { following = slug.value === ''; });", $html, $url);
        }
        // The saved slug is what an edit starts with, so the script finds it filled and leaves it alone.
        $this->assertStringContainsString('value="'.$existing->slug.'"', $this->get("/admin/podcasts/{$existing->id}/edit")->getContent());
        $this->assertDoesNotMatchRegularExpression('/id="slug"[^>]*value="[^"]+"/', $this->get('/admin/podcasts/create')->getContent());

        // And with the slug left empty (a Khmer title, or a form posted without the script) the server still makes it.
        $this->post('/admin/podcasts', ['tag_id' => $this->types()['health']->id, 'name' => 'Evening News Brief', 'slug' => ''])->assertRedirect();
        $this->assertNotNull(Program::where('slug', 'evening-news-brief')->first());
    }

    public function test_a_missing_title_says_title(): void
    {
        $this->signIn();

        $this->post('/admin/podcasts', ['tag_id' => $this->types()['health']->id, 'name' => '', 'slug' => ''])
            ->assertSessionHasErrors(['name' => 'The title field is required.']);
    }

    public function test_the_podcast_form_is_translated_into_khmer(): void
    {
        $this->signIn();
        $this->put('/admin/locale', ['locale' => 'km'])->assertRedirect();

        $form = $this->get('/admin/podcasts/create')->assertOk()->getContent();

        $this->assertStringContainsString('placeholder="ចំណងជើងផតខាស្ត"', $form);
        $this->assertStringContainsString('aria-label="ចំណងជើង"', $form);
        $this->assertStringContainsString('placeholder="បង្កើតពីចំណងជើងពេលអ្នកវាយ"', $form);
    }

    public function test_the_type_field_lists_the_podcast_tags(): void
    {
        $this->signIn();

        $form = $this->get('/admin/podcasts/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<label>Podcast type\s*<select id="tag_id"/', $form);
        $this->assertStringContainsString('Select a podcast type…', $form);
        // The tags under Podcast, and nothing else — no categories, no other trees.
        $select = substr($form, $start = strpos($form, '<select id="tag_id"'), strpos($form, '</select>', $start) - $start);
        foreach (['Health', 'Economy/Finance', 'Education', 'Politic', 'Civilization'] as $name) {
            $this->assertStringContainsString('>'.e($name).'</option>', $select);
        }
        foreach (['Podcast', 'News', 'Song', 'Ambient Music', 'Genre'] as $name) {
            $this->assertStringNotContainsString('>'.$name.'</option>', $select);
        }
        $this->assertStringNotContainsString('id="category_id"', $form);

        $list = $this->get('/admin/podcasts')->assertOk()->getContent();
        $this->assertStringContainsString('<th>Podcast type</th>', $list);

        $this->post('/admin/podcasts', ['name' => 'No Type', 'slug' => ''])
            ->assertSessionHasErrors(['tag_id' => 'The podcast type field is required.']);
    }

    public function test_a_new_program_is_filed_under_the_podcast_category_with_its_type(): void
    {
        $this->signIn();
        $health = $this->types()['health'];

        $this->post('/admin/podcasts', ['tag_id' => $health->id, 'name' => 'Healthy Habits', 'slug' => ''])->assertRedirect();

        $program = Program::where('slug', 'healthy-habits')->firstOrFail();
        $this->assertSame($health->id, $program->tag_id);
        $this->assertSame($this->category()->id, $program->category_id);

        // Only the tags under Podcast count as types.
        $this->post('/admin/podcasts', ['tag_id' => Tag::where('slug', 'song')->value('id'), 'name' => 'Not A Type', 'slug' => ''])
            ->assertSessionHasErrors('tag_id');
    }

    public function test_an_older_program_without_a_type_can_still_be_saved_and_a_type_files_it_as_a_podcast(): void
    {
        $this->signIn();
        $legacy = $this->podcast('Old News', Category::where('slug', 'news')->firstOrFail());

        $this->put("/admin/podcasts/{$legacy->id}", ['name' => 'Old News Renamed', 'slug' => ''])->assertRedirect();
        $legacy->refresh();
        $this->assertNull($legacy->tag_id);
        $this->assertSame('news', $legacy->category->slug);

        $this->put("/admin/podcasts/{$legacy->id}", ['tag_id' => $this->types()['politic']->id, 'name' => 'Old News Renamed', 'slug' => ''])->assertRedirect();
        $legacy->refresh();
        $this->assertSame($this->types()['politic']->id, $legacy->tag_id);
        $this->assertSame($this->category()->id, $legacy->category_id);
    }

    public function test_creating_a_podcast_takes_the_slug_from_its_name_and_opens_its_page(): void
    {
        $this->signIn();
        $payload = ['tag_id' => $this->types()['health']->id, 'name' => 'Morning Brief', 'slug' => '', 'is_published' => '1'];

        $response = $this->post('/admin/podcasts', $payload);
        $first = Program::where('slug', 'morning-brief')->firstOrFail();
        $response
            ->assertRedirect(route('admin.programs.edit', $first))
            ->assertSessionHas('status', 'Podcast created. Add its first episode below.');

        $this->post('/admin/podcasts', $payload)->assertRedirect();
        $this->assertDatabaseHas('programs', ['slug' => 'morning-brief-2']);
    }

    public function test_a_name_with_nothing_to_slug_falls_back_to_podcast(): void
    {
        $this->signIn();

        $this->post('/admin/podcasts', ['tag_id' => $this->types()['health']->id, 'name' => '???', 'slug' => ''])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', ['slug' => 'podcast']);
    }

    public function test_an_emptied_slug_keeps_the_existing_one_when_editing(): void
    {
        $this->signIn();
        $podcast = $this->podcast();

        $this->put("/admin/podcasts/{$podcast->id}", [
            'name' => 'Morning Brief Renamed',
            'slug' => '',
        ])->assertRedirect(route('admin.programs.index'));

        $this->assertSame('morning-brief', $podcast->fresh()->slug);
        $this->assertSame('Morning Brief Renamed', $podcast->fresh()->name);
    }

    public function test_the_list_counts_each_podcasts_episodes_and_how_many_are_live(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $this->episode($podcast, 'Live One', ['is_published' => true, 'published_at' => now()->subDay()]);
        $this->episode($podcast, 'Draft One');

        $this->get('/admin/podcasts')->assertOk()->assertSee('2 <small class="muted">· 1 live</small>', false);
    }

    public function test_a_podcast_page_lists_only_its_own_episodes(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $other = $this->podcast('Other Show');
        $ready = $this->episode($podcast, 'Pilot Episode', ['stream' => 'https://cdn.example.com/pilot.mp3', 'is_published' => true, 'published_at' => now()->subDay()]);
        $this->episode($podcast, 'Silent Episode');
        $this->episode($other, 'Someone Elses Episode');

        $this->get("/admin/podcasts/{$podcast->id}/edit")
            ->assertOk()
            ->assertSee('2 episodes')
            ->assertSee('Pilot Episode')
            ->assertSee('Silent Episode')
            ->assertDontSee('Someone Elses Episode')
            ->assertSee('No audio')
            ->assertSee('data-stream-url="https://cdn.example.com/pilot.mp3"', false)
            ->assertSee('Add episode')
            ->assertSee("/admin/media-contents/create?podcast={$podcast->id}", false)
            ->assertSee("/admin/media-contents/{$ready->id}/edit?podcast={$podcast->id}", false);
    }

    public function test_a_podcast_with_no_episodes_says_so(): void
    {
        $this->signIn();
        $podcast = $this->podcast();

        $this->get("/admin/podcasts/{$podcast->id}/edit")
            ->assertOk()
            ->assertSee('0 episodes')
            ->assertSee('No episodes yet.');
    }

    public function test_without_the_media_permission_the_episode_list_is_read_only(): void
    {
        $this->signIn(['view-programs', 'create-programs', 'edit-programs', 'delete-programs']);
        $podcast = $this->podcast();
        $this->episode($podcast, 'Pilot Episode');

        $this->get("/admin/podcasts/{$podcast->id}/edit")
            ->assertOk()
            ->assertSee('Pilot Episode')
            ->assertDontSee('Add episode')
            ->assertDontSee('/admin/media-contents/');
    }

    public function test_the_add_episode_form_starts_on_the_podcast_and_its_category(): void
    {
        $this->signIn();
        $health = Category::where('slug', 'health')->firstOrFail();
        $podcast = $this->podcast('Healthy Habits', $health);

        $response = $this->get("/admin/media-contents/create?podcast={$podcast->id}")
            ->assertOk()
            ->assertSee('New episode')
            ->assertSee('Episode title')
            ->assertSee('name="podcast" value="'.$podcast->id.'"', false)
            ->assertSee("/admin/podcasts/{$podcast->id}/edit", false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$health->id.'"[^>]* selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select id="program_id".*?<option value="'.$podcast->id.'"[^>]* selected>/s', $html);
    }

    public function test_the_tag_picker_marks_each_tree_with_its_category_id_so_the_form_can_narrow_it(): void
    {
        $this->signIn();

        $html = $this->get('/admin/media-contents/create')->assertOk()->getContent();
        $ambient = Category::where('slug', 'ambient-music')->firstOrFail();

        foreach (Category::where('id', '!=', $ambient->id)->get() as $category) {
            // The category option, and the top-level tag that sits under it.
            $this->assertMatchesRegularExpression('/<option value="'.$category->id.'"/', $html);
            $this->assertMatchesRegularExpression('/<(details|label)[^>]* data-category-id="'.$category->id.'"/', $html);
        }
        // Ambient Music has its tree in the picker like any category (it shows when "New Ambient Music" picks
        // that category), but it is not a category option here.
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$ambient->id.'"/', $html);
        $this->assertMatchesRegularExpression('/<(details|label)[^>]* data-category-id="'.$ambient->id.'"/', $html);
        // Nested tags aren't marked — only the trees themselves are switched.
        $this->assertSame(Category::count(), substr_count($html, ' data-category-id="'));
    }

    public function test_only_tags_from_the_categorys_own_tree_are_saved(): void
    {
        $this->signIn();
        $economy = Category::where('slug', 'economyfinance')->firstOrFail();
        $root = Tag::where('slug', 'economyfinance')->whereNull('parent_id')->firstOrFail();
        $local = Tag::where('parent_id', $root->id)->where('name', 'Local')->firstOrFail();
        $healthTopic = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();

        $this->post('/admin/media-contents', [
            'category_id' => $economy->id,
            'title' => 'Markets Today',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            // Another category's tag, as a stale form or a hand-made request could send.
            'tags' => [$root->id, $local->id, $healthTopic->id],
        ])->assertRedirect();

        $saved = MediaContent::where('slug', 'markets-today')->firstOrFail()->tags->pluck('id')->sort()->values()->all();
        $this->assertSame([$root->id, $local->id], $saved);
    }

    public function test_the_form_has_no_ambient_music_card_and_the_moods_are_in_the_tags_box(): void
    {
        $this->signIn();
        $moods = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->orderBy('id')->get();

        $html = $this->get('/admin/media-contents/create')->assertOk()->getContent();

        // No card of moods under the Description any more.
        $this->assertStringNotContainsString('data-ambient-card', $html);
        $this->assertStringNotContainsString('<h4>Ambient Music</h4>', $html);
        $this->assertStringNotContainsString('Tick the moods', $html);
        $this->assertStringNotContainsString('Ambient Music card', $html);

        // The moods are the Ambient Music tree of the Tags box, listed once, where the category picks it.
        foreach ($moods as $mood) {
            $this->assertSame(1, substr_count($html, 'name="tags[]" value="'.$mood->id.'"'));
        }
    }

    public function test_the_moods_an_ambient_music_item_has_are_ticked_in_the_tags_box_when_it_is_edited(): void
    {
        $this->signIn();
        $moods = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->orderBy('id')->get();
        $track = MediaContent::create([
            'category_id' => Category::where('slug', 'ambient-music')->firstOrFail()->id,
            'title' => 'Calm Waves', 'slug' => 'calm-waves', 'media_type' => 'audio',
        ]);
        $track->tags()->attach($moods[1]->id);

        $html = $this->get("/admin/media-contents/{$track->id}/edit")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="tags\[\]" value="'.$moods[1]->id.'" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="tags\[\]" value="'.$moods[0]->id.'" checked/', $html);
    }

    public function test_saving_a_song_keeps_the_moods_it_already_carries(): void
    {
        $this->signIn();
        $song = Category::where('slug', 'song')->firstOrFail();
        $mood = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->orderBy('id')->firstOrFail();
        $songRoot = Tag::where('slug', 'song')->whereNull('parent_id')->firstOrFail();
        $track = MediaContent::create(['category_id' => $song->id, 'title' => 'Calm Song', 'slug' => 'calm-song', 'media_type' => 'audio']);
        $track->tags()->sync([$mood->id]);

        // The form no longer lists the moods for a song, so it posts none of them back: they must survive.
        $this->put("/admin/media-contents/{$track->id}", [
            'category_id' => $song->id,
            'title' => 'Calm Song',
            'slug' => 'calm-song',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$songRoot->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing([$songRoot->id, $mood->id], $track->fresh()->tags->pluck('id')->all());
    }

    public function test_a_song_can_carry_ambient_moods_but_not_other_categories_tags(): void
    {
        $this->signIn();
        $song = Category::where('slug', 'song')->firstOrFail();
        $mood = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->orderBy('id')->firstOrFail();
        $songRoot = Tag::where('slug', 'song')->whereNull('parent_id')->firstOrFail();
        $healthTopic = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();

        $this->post('/admin/media-contents', [
            'category_id' => $song->id,
            'title' => 'Focus Song',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$songRoot->id, $mood->id, $healthTopic->id],
        ])->assertRedirect();

        $saved = MediaContent::where('slug', 'focus-song')->firstOrFail()->tags->pluck('id')->sort()->values()->all();
        $this->assertSame([$songRoot->id, $mood->id], $saved);
    }

    public function test_ambient_music_content_keeps_its_moods_and_they_can_be_unticked_in_the_tags_box(): void
    {
        $this->signIn();
        $ambient = Category::where('slug', 'ambient-music')->firstOrFail();
        $moods = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->firstOrFail()->children()->orderBy('id')->get();

        $this->post('/admin/media-contents', [
            'category_id' => $ambient->id,
            'title' => 'Deep Focus',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$moods[0]->id, $moods[3]->id],
        ])->assertRedirect();

        $saved = MediaContent::where('slug', 'deep-focus')->firstOrFail()->tags->pluck('id')->sort()->values()->all();
        $this->assertSame([$moods[0]->id, $moods[3]->id], $saved);

        // Filed under it already, the item keeps that category to edit, with its moods ticked in the Tags box.
        $track = MediaContent::where('slug', 'deep-focus')->firstOrFail();
        $this->get("/admin/media-contents/{$track->id}/edit")
            ->assertOk()
            ->assertSee('name="tags[]" value="'.$moods[0]->id.'" checked', false)
            ->assertDontSee('data-elsewhere', false)
            ->assertDontSee('data-tag-elsewhere-hint', false);

        // Unticking one there takes it off (an Ambient Music item's moods are all on the form).
        $this->put("/admin/media-contents/{$track->id}", [
            'category_id' => $ambient->id,
            'title' => 'Deep Focus',
            'slug' => 'deep-focus',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$moods[0]->id],
        ])->assertRedirect();
        $this->assertSame([$moods[0]->id], $track->fresh()->tags->pluck('id')->all());
    }

    public function test_the_category_select_does_not_offer_ambient_music(): void
    {
        $this->signIn();

        $create = $this->get('/admin/media-contents/create')->assertOk()->getContent();
        $select = substr($create, strpos($create, '<select id="category_id"'), 1500);
        $this->assertStringContainsString('>Song</option>', $select);
        $this->assertStringNotContainsString('>Ambient Music</option>', substr($select, 0, strpos($select, '</select>')));

        // A link that asks for it just starts with no category picked.
        $preselected = $this->get('/admin/media-contents/create?category=ambient-music')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<select id="category_id".*?<option value="\d+"[^>]* selected>/s', substr($preselected, 0, strpos($preselected, 'id="program_id"')));
    }

    public function test_a_category_without_a_tag_tree_keeps_whatever_is_ticked(): void
    {
        $this->signIn();
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
        $tag = Tag::where('slug', 'health')->whereNotNull('parent_id')->firstOrFail();

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'Free Form',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
            'tags' => [$tag->id],
        ])->assertRedirect();

        $this->assertSame([$tag->id], MediaContent::where('slug', 'free-form')->firstOrFail()->tags->pluck('id')->all());
    }

    public function test_the_program_select_carries_each_programs_category_so_the_form_can_follow_the_category(): void
    {
        $this->signIn();
        $health = Category::where('slug', 'health')->firstOrFail();
        $healthShow = $this->podcast('Healthy Habits', $health);
        $generalShow = $this->podcast('Morning Brief');

        $html = $this->get('/admin/media-contents/create')->assertOk()->getContent();
        $select = substr($html, $start = strpos($html, '<select id="program_id"'), strpos($html, '</select>', $start) - $start);

        $this->assertStringContainsString('<option value="'.$healthShow->id.'" data-category-id="'.$health->id.'"', $select);
        $this->assertStringContainsString('<option value="'.$generalShow->id.'" data-category-id="'.$this->category()->id.'"', $select);
        // The option reads as the program's name — the category is the select above.
        $this->assertStringContainsString('>Healthy Habits</option>', $select);
        $this->assertStringNotContainsString('Health — Healthy Habits', $select);
        $this->assertStringContainsString('<option value="">No podcast</option>', $select);
    }

    public function test_a_new_episode_starts_public_but_other_new_media_stays_private(): void
    {
        $this->signIn();
        $podcast = $this->podcast();

        $this->get("/admin/media-contents/create?podcast={$podcast->id}")
            ->assertOk()
            ->assertSee('<option value="public" selected>', false);
        $this->get('/admin/media-contents/create')
            ->assertOk()
            ->assertSee('<option value="private" selected>', false);
    }

    public function test_the_media_form_without_a_podcast_is_unchanged(): void
    {
        $this->signIn();

        $this->get('/admin/media-contents/create')
            ->assertOk()
            ->assertSee('New media content')
            ->assertDontSee('name="podcast"', false);
    }

    public function test_saving_an_episode_from_a_podcast_page_returns_to_that_page(): void
    {
        $this->signIn();
        $podcast = $this->podcast();

        $this->post('/admin/media-contents', [
            'podcast' => $podcast->id,
            'category_id' => $podcast->category_id,
            'program_id' => $podcast->id,
            'title' => 'Pilot Episode',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'public',
            'stream_url' => 'https://cdn.example.com/pilot.mp3',
        ])->assertRedirect(route('admin.programs.edit', $podcast))->assertSessionHas('status');

        $episode = MediaContent::where('slug', 'pilot-episode')->firstOrFail();
        $this->assertSame($podcast->id, $episode->program_id);
        $this->assertSame('ready', $episode->processing_status);

        $this->put("/admin/media-contents/{$episode->id}", [
            'podcast' => $podcast->id,
            'category_id' => $podcast->category_id,
            'program_id' => $podcast->id,
            'title' => 'Pilot Episode',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.programs.edit', $podcast));

        $this->assertSame('pilot-episode', $episode->fresh()->slug);
    }

    public function test_editing_an_episode_from_its_podcast_page_locks_the_category_and_podcast_and_hides_the_artist(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, 'Pilot Episode');

        $html = $this->get("/admin/media-contents/{$episode->id}/edit?podcast={$podcast->id}")->assertOk()->getContent();

        // Both selects show their value but are disabled, so hidden fields carry the values to the save.
        $this->assertMatchesRegularExpression('/<select id="category_id" disabled>/', $html);
        $this->assertMatchesRegularExpression('/<select id="program_id" disabled>/', $html);
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$podcast->category_id.'">', $html);
        $this->assertStringContainsString('<input type="hidden" name="program_id" value="'.$podcast->id.'">', $html);
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$podcast->category_id.'"[^>]* selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select id="program_id".*?<option value="'.$podcast->id.'"[^>]* selected>/s', $html);
        // No Artist field, and no shortcut to make another podcast.
        $this->assertStringNotContainsString('id="artist_id"', $html);
        $this->assertStringNotContainsString('name="artist_id"', $html);
        $this->assertStringNotContainsString(route('admin.programs.create'), $html);
    }

    public function test_the_add_episode_form_locks_the_category_and_podcast_and_hides_the_artist(): void
    {
        $this->signIn();
        $health = Category::where('slug', 'health')->firstOrFail();
        $podcast = $this->podcast('Healthy Habits', $health);

        $html = $this->get("/admin/media-contents/create?podcast={$podcast->id}")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<select id="category_id" disabled>/', $html);
        $this->assertMatchesRegularExpression('/<select id="program_id" disabled>/', $html);
        $this->assertStringContainsString('<input type="hidden" name="category_id" value="'.$health->id.'">', $html);
        $this->assertStringContainsString('<input type="hidden" name="program_id" value="'.$podcast->id.'">', $html);
        $this->assertMatchesRegularExpression('/<select id="category_id".*?<option value="'.$health->id.'"[^>]* selected>/s', $html);
        $this->assertMatchesRegularExpression('/<select id="program_id".*?<option value="'.$podcast->id.'"[^>]* selected>/s', $html);
        $this->assertStringNotContainsString('id="artist_id"', $html);
        $this->assertStringNotContainsString(route('admin.programs.create'), $html);
    }

    public function test_the_add_episode_form_with_an_unknown_podcast_is_the_plain_form(): void
    {
        $this->signIn();

        $html = $this->get('/admin/media-contents/create?podcast=9999')->assertOk()->getContent();

        $this->assertStringContainsString('<select id="category_id" name="category_id" required>', $html);
        $this->assertStringContainsString('<select id="program_id" name="program_id">', $html);
        $this->assertStringContainsString('<select id="artist_id" name="artist_id">', $html);
    }

    public function test_the_edit_form_without_a_podcast_keeps_its_selects_and_artist(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, 'Pilot Episode');

        $html = $this->get("/admin/media-contents/{$episode->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('<select id="category_id" name="category_id" required>', $html);
        $this->assertStringContainsString('<select id="program_id" name="program_id">', $html);
        $this->assertStringContainsString('<select id="artist_id" name="artist_id">', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="program_id"', $html);
    }

    public function test_an_episode_opened_from_a_podcast_keeps_its_own_podcast_even_if_the_address_names_another(): void
    {
        $this->signIn();
        $podcast = $this->podcast('Morning Brief');
        $other = $this->podcast('Evening Brief');
        $episode = $this->episode($podcast, 'Pilot Episode');

        $this->get("/admin/media-contents/{$episode->id}/edit?podcast={$other->id}")
            ->assertOk()
            ->assertSee('<input type="hidden" name="program_id" value="'.$podcast->id.'">', false);
    }

    public function test_saving_an_episode_from_its_podcast_page_keeps_its_artist_podcast_and_category(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $artist = \Src\Catalog\Models\Artist::create(['name' => 'Dr. Host', 'slug' => 'dr-host']);
        $episode = $this->episode($podcast, 'Pilot Episode', ['artist_id' => $artist->id]);

        // What the locked form posts: the hidden category and podcast, and no artist at all.
        $this->put("/admin/media-contents/{$episode->id}", [
            'podcast' => $podcast->id,
            'category_id' => $podcast->category_id,
            'program_id' => $podcast->id,
            'title' => 'Pilot Episode, renamed',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.programs.edit', $podcast));

        $episode->refresh();
        $this->assertSame('Pilot Episode, renamed', $episode->title);
        $this->assertSame($artist->id, $episode->artist_id);
        $this->assertSame($podcast->id, $episode->program_id);
        $this->assertSame($podcast->category_id, $episode->category_id);
    }

    public function test_episode_slugs_are_made_unique_and_an_unknown_podcast_falls_back_to_the_list(): void
    {
        $this->signIn();
        $podcast = $this->podcast();
        $this->episode($podcast, 'Pilot Episode');

        $this->post('/admin/media-contents', [
            'podcast' => 9999,
            'category_id' => $podcast->category_id,
            'title' => 'Pilot Episode',
            'slug' => '',
            'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect(route('admin.media-contents.index'));

        $this->assertDatabaseHas('media_contents', ['slug' => 'pilot-episode-2']);
    }
}
