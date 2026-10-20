<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Tag;
use Tests\TestCase;

class ApiMusicTest extends TestCase
{
    use RefreshDatabase;

    private Category $song;

    protected function setUp(): void
    {
        parent::setUp();
        // The categories are seeded by the catalog migrations.
        $this->song = Category::where('slug', 'song')->firstOrFail();
    }

    private function artist(string $name, array $overrides = []): Artist
    {
        return Artist::create(['name' => $name, 'slug' => str($name)->slug()->toString(), ...$overrides]);
    }

    private function tag(string $name, ?Tag $parent = null): Tag
    {
        return Tag::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id]);
    }

    private function song(string $title, ?Artist $artist, int $plays = 0, array $overrides = []): MediaContent
    {
        $song = MediaContent::create([
            'category_id' => $this->song->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            'is_published' => true,
            'processing_status' => 'ready',
            'published_at' => now()->subDay(),
            'play_count' => $plays,
            ...$overrides,
        ]);

        if ($artist) {
            $song->artists()->attach($artist);
        }

        return $song;
    }

    public function test_artists_are_listed_most_played_first(): void
    {
        $quiet = $this->artist('Quiet One');
        $loud = $this->artist('Loud One', ['photo' => 'https://example.com/loud.png']);
        $this->song('Q1', $quiet, 5);
        $this->song('L1', $loud, 40);
        $this->song('L2', $loud, 60);

        $this->getJson('/api/artists')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Loud One')
            ->assertJsonPath('data.0.photo_url', 'https://example.com/loud.png')
            ->assertJsonPath('data.1.name', 'Quiet One')
            ->assertJsonPath('data.1.photo_url', null);
    }

    public function test_artists_need_something_playable_and_to_be_active(): void
    {
        $live = $this->artist('Live');
        $this->song('Live One', $live, 1);

        $hiddenOnly = $this->artist('Hidden Only');
        $this->song('Draft', $hiddenOnly, 99, ['is_published' => false]);
        $this->song('Not Yet', $hiddenOnly, 99, ['published_at' => now()->addDay()]);

        $inactive = $this->artist('Inactive', ['status' => 'inactive']);
        $this->song('Inactive One', $inactive, 99);

        $this->artist('No Songs');

        $this->getJson('/api/artists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Live');
    }

    public function test_artists_can_be_narrowed_by_category_and_by_tag_with_descendants(): void
    {
        $local = $this->tag('Local');
        $genre = $this->tag('Genre', $local);
        $rap = $this->tag('Rap', $genre);
        $international = $this->tag('International');

        $nuth = $this->artist('Nuth');
        $this->song('Rap One', $nuth, 10)->tags()->attach($rap);
        $abroad = $this->artist('Abroad');
        $this->song('Abroad One', $abroad, 50)->tags()->attach($international);

        $podcaster = $this->artist('Podcaster');
        $this->song('Talk', $podcaster, 999, ['category_id' => Category::where('slug', 'podcast')->value('id')]);

        $this->getJson('/api/artists?category=song')->assertJsonCount(2, 'data');

        $this->getJson('/api/artists?category=song&tag_ids='.$local->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nuth');

        $this->getJson('/api/artists?category=song&tag_ids='.$local->id.','.$international->id)
            ->assertJsonCount(2, 'data');
    }

    public function test_artists_honour_the_limit(): void
    {
        foreach (['A', 'B', 'C'] as $i => $name) {
            $this->song("Song $name", $this->artist("Artist $name"), 10 - $i);
        }

        $this->getJson('/api/artists?limit=2')->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Artist A');
    }

    public function test_media_contents_filter_by_artist(): void
    {
        $nuth = $this->artist('Nuth');
        $neo = $this->artist('NEO');
        $this->song('By Nuth', $nuth);
        $this->song('By Neo', $neo);

        $this->getJson('/api/media-contents?artist_id='.$nuth->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'By Nuth');
    }

    public function test_an_item_with_several_artists_lists_all_of_them_and_is_found_through_each(): void
    {
        $nuth = $this->artist('Nuth', ['photo' => 'https://example.com/nuth.png']);
        $neo = $this->artist('NEO');
        $this->song('Duet', $nuth)->artists()->attach($neo);
        $this->song('Solo By Neo', $neo);

        $duet = collect($this->getJson('/api/media-contents')->assertOk()->json('data'))->keyBy('title')['Duet'];

        // Names joined (A to Z), the first one's photo, and the whole list.
        $this->assertSame('NEO, Nuth', $duet['artist_name']);
        $this->assertSame(['NEO', 'Nuth'], array_column($duet['artists'], 'name'));
        $this->assertSame([null, 'https://example.com/nuth.png'], array_column($duet['artists'], 'photo_url'));
        $this->assertNull($duet['artist_photo_url'], 'the first artist (NEO) has no photo');

        // The artist filter and the search both match any of the artists, and an item is listed once.
        foreach ([$nuth, $neo] as $artist) {
            $this->assertContains('Duet', array_column($this->getJson('/api/media-contents?artist_id='.$artist->id)->assertOk()->json('data'), 'title'));
        }
        $this->assertSame(['Duet', 'Solo By Neo'], collect($this->getJson('/api/media-contents?q=neo')->json('data'))->pluck('title')->sort()->values()->all());
        $this->assertCount(1, $this->getJson('/api/media-contents?artist_id='.$nuth->id)->json('data'));

        // One item's page credits both too.
        $id = MediaContent::where('title', 'Duet')->value('id');
        $this->getJson("/api/media-contents/{$id}")->assertOk()->assertJsonPath('data.artist_name', 'NEO, Nuth')->assertJsonCount(2, 'data.artists');
    }

    public function test_media_contents_carry_the_artists_name_and_photo(): void
    {
        $this->song('With Photo', $this->artist('Nuth', ['photo' => 'https://example.com/nuth.png']));
        $this->song('Without Photo', $this->artist('NEO'));

        $rows = collect($this->getJson('/api/media-contents')->assertOk()->json('data'))->keyBy('title');

        $this->assertSame('Nuth', $rows['With Photo']['artist_name']);
        $this->assertSame('https://example.com/nuth.png', $rows['With Photo']['artist_photo_url']);
        $this->assertNull($rows['Without Photo']['artist_photo_url']);
    }

    public function test_media_contents_can_be_searched_by_title_or_artist(): void
    {
        $nuth = $this->artist('Nuth');
        $this->song('Midnight Rain', $this->artist('Someone Else'));
        $this->song('Sunrise', $nuth);
        $this->song('100% Rain', null);

        $titles = fn (string $q) => collect($this->getJson('/api/media-contents?q='.urlencode($q))->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();

        $this->assertSame(['100% Rain', 'Midnight Rain'], $titles('rain'));
        $this->assertSame(['Sunrise'], $titles(' nuth '));
        // A % in the search is a plain character, not a wildcard.
        $this->assertSame(['100% Rain'], $titles('%'));
        $this->assertSame([], $titles('nothing like it'));
    }

    public function test_media_contents_filter_by_several_tags_and_their_descendants(): void
    {
        $local = $this->tag('Local');
        $localRap = $this->tag('Local Rap', $local);
        $international = $this->tag('International');
        $other = $this->tag('Other');

        $this->song('Local Song', null)->tags()->attach($localRap);
        $this->song('Abroad Song', null)->tags()->attach($international);
        $this->song('Other Song', null)->tags()->attach($other);
        $this->song('Untagged Song', null);

        $titles = fn (string $query) => collect($this->getJson('/api/media-contents?'.$query)->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();

        $this->assertSame(['Local Song'], $titles('tag_ids='.$local->id));
        $this->assertSame(['Abroad Song', 'Local Song'], $titles('tag_ids='.$local->id.','.$international->id));
        $this->assertCount(4, $titles('category=song'));
    }

    public function test_a_lookup_by_ids_returns_every_item_not_a_page(): void
    {
        $ids = [];
        foreach (range(1, 25) as $i) {
            $ids[] = $this->song("Song $i", null)->id;
        }

        $this->getJson('/api/media-contents?ids='.implode(',', $ids))
            ->assertOk()
            ->assertJsonCount(25, 'data');

        // Still only what is publicly visible, and only the ids asked for.
        $hidden = $this->song('Hidden', null, 0, ['is_published' => false]);
        $this->getJson('/api/media-contents?ids='.$ids[0].','.$hidden->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Song 1');
    }

    public function test_the_songs_list_can_combine_artist_tag_and_popularity(): void
    {
        $rap = $this->tag('Rap');
        $nuth = $this->artist('Nuth');
        $this->song('Quiet Rap', $nuth, 1)->tags()->attach($rap);
        $this->song('Loud Rap', $nuth, 9)->tags()->attach($rap);
        $this->song('Nuth Pop', $nuth, 50);
        $this->song('Other Rap', $this->artist('Other'), 99)->tags()->attach($rap);

        $this->getJson('/api/media-contents?category=song&sort=trending&artist_id='.$nuth->id.'&tag_ids='.$rap->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Loud Rap')
            ->assertJsonPath('data.1.title', 'Quiet Rap');
    }

    public function test_trending_podcasts_exclude_other_content_categories(): void
    {
        $podcast = Category::where('slug', 'podcast')->firstOrFail();

        $this->song('Popular Song', null, 1000);
        $this->song('Quiet Podcast', null, 2, ['category_id' => $podcast->id]);
        $this->song('Popular Podcast', null, 20, ['category_id' => $podcast->id]);

        $this->getJson('/api/media-contents?category=podcast&sort=trending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Popular Podcast')
            ->assertJsonPath('data.1.title', 'Quiet Podcast');
    }
}
