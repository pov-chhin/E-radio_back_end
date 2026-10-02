<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Tests\TestCase;

class ApiProgramTest extends TestCase
{
    use RefreshDatabase;

    private Category $podcast;

    protected function setUp(): void
    {
        parent::setUp();
        // The categories are seeded by the catalog migrations.
        $this->podcast = Category::where('slug', 'podcast')->firstOrFail();
    }

    private function program(string $name, array $overrides = [], ?Category $category = null): Program
    {
        return Program::create([
            'category_id' => ($category ?? $this->podcast)->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_published' => true,
            ...$overrides,
        ]);
    }

    private function episode(Program $program, string $title, array $overrides = []): MediaContent
    {
        return MediaContent::create([
            'category_id' => $program->category_id,
            'program_id' => $program->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            'is_published' => true,
            'processing_status' => 'ready',
            'published_at' => now()->subDay(),
            ...$overrides,
        ]);
    }

    public function test_index_lists_only_published_shows_with_a_playable_episode(): void
    {
        $live = $this->program('Live Show');
        $this->episode($live, 'Live One');

        $draft = $this->program('Draft Show', ['is_published' => false]);
        $this->episode($draft, 'Draft One');

        $empty = $this->program('Empty Show');
        $this->episode($empty, 'Processing', ['processing_status' => 'processing']);
        $this->episode($empty, 'Scheduled', ['published_at' => now()->addDay()]);
        $this->episode($empty, 'Private', ['is_published' => false]);

        $this->getJson('/api/programs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Live Show')
            ->assertJsonPath('data.0.episode_count', 1)
            ->assertJsonPath('data.0.category.slug', 'podcast');
    }

    public function test_index_can_be_searched_by_a_shows_name(): void
    {
        $this->episode($this->program('Morning Drive'), 'One');
        $this->episode($this->program('Evening Calm'), 'Two');

        $this->getJson('/api/programs?q='.urlencode(' drive '))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Morning Drive');

        $this->getJson('/api/programs?q=nothing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_show_takes_its_host_and_cover_from_the_newest_episode(): void
    {
        $show = $this->program('Psychology & Growth');
        $old = Artist::create(['name' => 'Old Host', 'slug' => 'old-host']);
        $new = Artist::create(['name' => 'Dr. Serey Roth', 'slug' => 'dr-serey-roth']);
        $this->episode($show, 'Older', ['artist_id' => $old->id, 'thumbnail' => 'https://example.com/old.png', 'published_at' => now()->subDays(3)]);
        $this->episode($show, 'Newer', ['artist_id' => $new->id, 'thumbnail' => 'https://example.com/new.png', 'published_at' => now()->subHour()]);

        $this->getJson('/api/programs')
            ->assertOk()
            ->assertJsonPath('data.0.host_name', 'Dr. Serey Roth')
            ->assertJsonPath('data.0.cover_image_url', 'https://example.com/new.png')
            ->assertJsonPath('data.0.episode_count', 2);
    }

    public function test_the_shows_own_cover_wins_over_an_episode_picture(): void
    {
        $show = $this->program('Covered', ['cover_image' => 'https://example.com/cover.png']);
        $this->episode($show, 'One', ['thumbnail' => 'https://example.com/ep.png']);

        $this->getJson('/api/programs')->assertJsonPath('data.0.cover_image_url', 'https://example.com/cover.png');
    }

    public function test_index_puts_the_most_recently_updated_show_first(): void
    {
        $stale = $this->program('Stale');
        $this->episode($stale, 'Stale One', ['published_at' => now()->subWeek()]);
        $fresh = $this->program('Fresh');
        $this->episode($fresh, 'Fresh One', ['published_at' => now()->subHour()]);

        $this->getJson('/api/programs')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Fresh')
            ->assertJsonPath('data.1.name', 'Stale');
    }

    public function test_index_filters_by_category_and_by_podcast_type_including_descendants(): void
    {
        $news = Category::where('slug', 'news')->firstOrFail();
        $newsShow = $this->program('News Show', category: $news);
        $this->episode($newsShow, 'News One');

        $podcastRoot = Tag::where('slug', 'podcast')->whereNull('parent_id')->firstOrFail();
        $types = Tag::where('parent_id', $podcastRoot->id)->get()->keyBy('slug');
        $sleep = Tag::create(['name' => 'Sleep', 'slug' => 'sleep', 'parent_id' => $types['health']->id, 'category_id' => $podcastRoot->category_id]);
        $this->episode($this->program('Sleep Show', ['tag_id' => $sleep->id]), 'Sleep One');
        $this->episode($this->program('Health Show', ['tag_id' => $types['health']->id]), 'Health One');
        $this->episode($this->program('Other Show'), 'Other One');

        $this->getJson('/api/programs?category=news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'News Show');

        // A type takes the shows filed under it or under a tag nested in it; a show with no type is in neither.
        $names = collect($this->getJson('/api/programs?tag_id='.$types['health']->id)->assertOk()->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Health Show', 'Sleep Show'], $names);
        $this->getJson('/api/programs?tag_id='.$types['education']->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_show_carries_its_podcast_type(): void
    {
        $podcastRoot = Tag::where('slug', 'podcast')->whereNull('parent_id')->firstOrFail();
        $economy = Tag::where('parent_id', $podcastRoot->id)->where('slug', 'economyfinance')->firstOrFail();
        $typed = $this->program('Markets Show', ['tag_id' => $economy->id]);
        $this->episode($typed, 'Markets One');
        $plain = $this->program('Plain Show');
        $this->episode($plain, 'Plain One');

        $index = collect($this->getJson('/api/programs')->assertOk()->json('data'))->keyBy('name');
        $this->assertSame(
            ['id' => (string) $economy->id, 'name' => 'Economy/Finance', 'slug' => 'economyfinance'],
            $index['Markets Show']['type'],
        );
        $this->assertNull($index['Plain Show']['type']);

        $this->getJson("/api/programs/{$typed->id}")->assertOk()->assertJsonPath('data.type.name', 'Economy/Finance');
    }

    public function test_show_returns_the_playable_episodes_newest_first(): void
    {
        $show = $this->program('Psychology & Growth', ['description' => 'Mind and well-being.']);
        $this->episode($show, 'Second', ['published_at' => now()->subDays(2)]);
        $this->episode($show, 'First', ['published_at' => now()->subHour()]);
        $this->episode($show, 'Hidden', ['is_published' => false]);
        $this->episode($show, 'Not Yet', ['published_at' => now()->addDay()]);

        $this->getJson('/api/programs/'.$show->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Psychology & Growth')
            ->assertJsonPath('data.description', 'Mind and well-being.')
            ->assertJsonPath('data.episode_count', 2)
            ->assertJsonCount(2, 'data.episodes')
            ->assertJsonPath('data.episodes.0.title', 'First')
            ->assertJsonPath('data.episodes.1.title', 'Second');
    }

    public function test_show_hides_an_unpublished_program(): void
    {
        $draft = $this->program('Draft Show', ['is_published' => false]);
        $this->episode($draft, 'Draft One');

        $this->getJson('/api/programs/'.$draft->id)->assertNotFound();
    }
}
