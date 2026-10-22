<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Tests\TestCase;

class ApiHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('api:home:v1');
    }

    private function content(string $title, string $category, array $overrides = []): MediaContent
    {
        return MediaContent::create([
            'category_id' => Category::where('slug', $category)->firstOrFail()->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'media_type' => 'audio',
            'stream' => 'https://example.com/'.str($title)->slug().'.mp3',
            'is_published' => true,
            'processing_status' => 'ready',
            'published_at' => now()->subMinute(),
            ...$overrides,
        ]);
    }

    public function test_home_returns_the_sections_the_app_needs(): void
    {
        $this->content('Latest News', 'news', ['play_count' => 20]);
        $this->content('Latest Song', 'song', ['play_count' => 10]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, public, stale-while-revalidate=60')
            ->assertJsonStructure(['data' => [
                'stations', 'trending', 'new_releases', 'feeds' => ['news', 'economyfinance', 'health', 'song'],
                'programs', 'categories', 'tags', 'artists',
            ]])
            ->assertJsonPath('data.trending.0.title', 'Latest News')
            ->assertJsonPath('data.feeds.news.0.title', 'Latest News')
            ->assertJsonPath('data.feeds.song.0.title', 'Latest Song');
    }

    public function test_home_excludes_content_that_is_not_publicly_playable(): void
    {
        $this->content('Visible', 'news');
        $this->content('Draft', 'news', ['is_published' => false]);
        $this->content('Processing', 'news', ['processing_status' => 'processing']);
        $this->content('Scheduled', 'news', ['published_at' => now()->addDay()]);

        $titles = collect($this->getJson('/api/home')->assertOk()->json('data.feeds.news'))->pluck('title')->all();

        $this->assertSame(['Visible'], $titles);
    }

    public function test_home_response_is_cached_for_one_minute(): void
    {
        $this->content('First', 'news');
        $this->getJson('/api/home')->assertJsonPath('data.feeds.news.0.title', 'First');

        $this->content('Second', 'news', ['published_at' => now()]);
        $this->getJson('/api/home')->assertJsonMissing(['title' => 'Second']);

        $this->travel(61)->seconds();
        $this->getJson('/api/home')->assertJsonPath('data.feeds.news.0.title', 'Second');
    }
}
