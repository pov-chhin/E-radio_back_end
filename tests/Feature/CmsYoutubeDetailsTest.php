<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Services\YoutubeVideoDetails;
use Src\UserManagement\Models\Permission;
use Src\UserManagement\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Fill from YouTube" on the media form: a pasted link's title, length and cover are read from the YouTube Data
 * API v3 (faked here — no network, no real key). The API never carries audio; that stays with yt-dlp.
 */
class CmsYoutubeDetailsTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private const LINK = 'https://youtu.be/D5-Y6_JABoI?si=O-_C8q7r8HQ0jqT3';

    private const ENDPOINT = '/admin/media-contents/youtube-details';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.youtube_data.key' => 'TEST-KEY']);
        $this->actingAs($this->createAdmin(['view-media-content', 'create-media-content', 'edit-media-content']), 'admin');
    }

    /** What videos.list answers for one video. */
    private function video(array $overrides = []): array
    {
        return ['items' => [array_replace_recursive([
            'id' => 'D5-Y6_JABoI',
            'snippet' => [
                'title' => 'Angkor Wat — the full story',
                'channelTitle' => 'Khmer History',
                'liveBroadcastContent' => 'none',
                'thumbnails' => [
                    'default' => ['url' => 'https://i.ytimg.com/vi/D5-Y6_JABoI/default.jpg'],
                    'high' => ['url' => 'https://i.ytimg.com/vi/D5-Y6_JABoI/hqdefault.jpg'],
                    'maxres' => ['url' => 'https://i.ytimg.com/vi/D5-Y6_JABoI/maxresdefault.jpg'],
                ],
            ],
            'contentDetails' => ['duration' => 'PT1H2M3S'],
        ], $overrides)]];
    }

    private function fakeYoutube(array $body, int $status = 200): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response($body, $status)]);
    }

    private function details(string $url = self::LINK)
    {
        return $this->postJson(self::ENDPOINT, ['url' => $url]);
    }

    public function test_the_title_length_and_largest_cover_come_from_the_youtube_data_api(): void
    {
        $this->fakeYoutube($this->video());

        $this->details()->assertOk()->assertExactJson([
            'title' => 'Angkor Wat — the full story',
            'channel' => 'Khmer History',
            'duration_seconds' => 3723,
            'thumbnail_url' => 'https://i.ytimg.com/vi/D5-Y6_JABoI/maxresdefault.jpg',
            'is_live' => false,
        ]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://www.googleapis.com/youtube/v3/videos?')
                && $query['id'] === 'D5-Y6_JABoI'
                && $query['part'] === 'snippet,contentDetails'
                && $query['key'] === 'TEST-KEY';
        });
    }

    public function test_the_cover_falls_back_to_the_largest_one_the_video_has(): void
    {
        $this->fakeYoutube($this->video(['snippet' => ['thumbnails' => ['maxres' => null]]]));

        $this->details()->assertOk()->assertJsonPath('thumbnail_url', 'https://i.ytimg.com/vi/D5-Y6_JABoI/hqdefault.jpg');
    }

    public function test_a_live_broadcast_is_flagged_and_has_no_length(): void
    {
        $this->fakeYoutube($this->video(['snippet' => ['liveBroadcastContent' => 'live'], 'contentDetails' => ['duration' => 'P0D']]));

        $this->details()->assertOk()->assertJsonPath('is_live', true)->assertJsonPath('duration_seconds', null);
    }

    #[DataProvider('durations')]
    public function test_iso_durations_become_seconds(mixed $iso, ?int $seconds): void
    {
        $this->assertSame($seconds, YoutubeVideoDetails::seconds($iso));
    }

    public static function durations(): array
    {
        return [
            'minutes and seconds' => ['PT4M13S', 253],
            'an hour' => ['PT1H', 3600],
            'seconds only' => ['PT45S', 45],
            'over a day' => ['P1DT1H', 90000],
            'a live broadcast' => ['P0D', null],
            'nonsense' => ['four minutes', null],
            'empty' => ['', null],
            'missing' => [null, null],
        ];
    }

    public function test_without_a_key_nothing_is_sent_to_youtube_and_the_admin_is_told_what_to_set(): void
    {
        config(['services.youtube_data.key' => null]);
        Http::fake();

        $this->details()->assertStatus(422)->assertJsonPath('message', fn (string $message) => str_contains($message, 'YOUTUBE_API_KEY'));
        Http::assertNothingSent();
    }

    public function test_a_link_that_is_not_a_youtube_video_is_refused_before_any_request(): void
    {
        Http::fake();

        $this->details('https://example.com/watch?v=D5-Y6_JABoI')->assertStatus(422)->assertJsonPath('message', 'That is not a YouTube video link.');
        $this->postJson(self::ENDPOINT, ['url' => 'not a link'])->assertStatus(422)->assertJsonValidationErrors('url');
        Http::assertNothingSent();
    }

    public function test_a_private_or_deleted_video_is_reported(): void
    {
        $this->fakeYoutube(['items' => []]);

        $this->details()->assertStatus(422)->assertJsonPath('message', fn (string $message) => str_contains($message, 'private or deleted'));
    }

    #[DataProvider('failures')]
    public function test_a_refusal_from_youtube_is_explained(int $status, string $reason, string $expected): void
    {
        $this->fakeYoutube(['error' => ['code' => $status, 'message' => 'x', 'errors' => [['reason' => $reason]]]], $status);

        $response = $this->details()->assertStatus(422);
        $this->assertStringContainsString($expected, $response->json('message'));
        $this->assertStringNotContainsString('TEST-KEY', $response->getContent());
    }

    public static function failures(): array
    {
        return [
            'quota used up' => [403, 'quotaExceeded', 'quota'],
            'bad key' => [400, 'keyInvalid', 'not valid'],
            'API not enabled' => [403, 'accessNotConfigured', 'not turned on'],
            'key restricted to other addresses' => [403, 'ipRefererBlocked', 'restricted'],
            'anything else' => [500, 'backendError', 'HTTP 500'],
        ];
    }

    public function test_an_unreachable_youtube_never_leaks_the_key_in_the_message(): void
    {
        // Laravel's connection errors quote the request address, which carries the key.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://www.googleapis.com/youtube/v3/videos?id=D5-Y6_JABoI&key=TEST-KEY'));

        $response = $this->details()->assertStatus(422);
        $this->assertStringContainsString('Could not reach YouTube', $response->json('message'));
        $this->assertStringNotContainsString('TEST-KEY', $response->getContent());
    }

    public function test_whoever_may_only_edit_media_content_can_use_it_and_others_cannot(): void
    {
        $this->fakeYoutube($this->video());

        $editOnly = Role::create(['slug' => 'edit-only', 'name' => 'Edit only']);
        $editOnly->permissions()->attach(Permission::firstOrCreate(['slug' => 'edit-media-content'], ['name' => 'edit-media-content'])->id);
        $editor = $this->createAdmin();
        $editor->roles()->attach($editOnly->id);

        $this->actingAs($editor, 'admin');
        $this->details()->assertOk();

        $this->actingAs($this->createAdmin(), 'admin');
        $this->details()->assertForbidden();
    }

    public function test_the_form_offers_the_button_and_auto_fills_only_when_a_key_is_set(): void
    {
        $this->get('/admin/media-contents/create')
            ->assertOk()
            ->assertSee('data-youtube-fill', false)
            ->assertSee('data-auto="1"', false)
            ->assertSee(self::ENDPOINT, false)
            ->assertSee('Fill from YouTube');

        config(['services.youtube_data.key' => null]);

        $this->get('/admin/media-contents/create')->assertOk()->assertSee('data-auto="0"', false);
    }
}
