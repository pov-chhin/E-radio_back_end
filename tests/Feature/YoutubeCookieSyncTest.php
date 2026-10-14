<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Src\Catalog\Jobs\FetchYoutubeAudio;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Setting\Models\StorageSetting;
use Tests\TestCase;

/**
 * The cookie-sync browser extension posts a signed-in browser's YouTube cookies to the server on its own. The address
 * only exists with a long secret token, takes nothing without it, and keeps only YouTube and Google cookies.
 */
class YoutubeCookieSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a-long-random-secret-token-for-the-tests-0123456789';

    private const URL = '/api/youtube-cookies/sync';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['services.youtube_cookie_sync.token' => self::TOKEN]);
    }

    /** @return list<array<string, mixed>> */
    private function cookies(): array
    {
        return [
            ['domain' => '.youtube.com', 'name' => 'SID', 'value' => 'first-sid-value', 'path' => '/', 'secure' => true, 'httpOnly' => false, 'hostOnly' => false, 'session' => false, 'expirationDate' => 2147483647.5],
            ['domain' => '.youtube.com', 'name' => '__Secure-3PSID', 'value' => 'psid-secret-value', 'path' => '/', 'secure' => true, 'httpOnly' => true, 'hostOnly' => false, 'session' => false, 'expirationDate' => 2147483647],
            ['domain' => '.google.com', 'name' => 'LOGIN_INFO', 'value' => 'login-info', 'path' => '/', 'secure' => true, 'httpOnly' => true, 'hostOnly' => false, 'session' => true],
            ['domain' => '.facebook.com', 'name' => 'c_user', 'value' => 'other-site-secret', 'path' => '/', 'secure' => true],
        ];
    }

    private function sync(array $cookies, ?string $token = self::TOKEN)
    {
        return $this->postJson(self::URL, ['cookies' => $cookies], $token === null ? [] : ['X-Cookie-Sync-Token' => $token]);
    }

    public function test_the_address_does_not_exist_until_a_long_token_is_configured(): void
    {
        foreach ([null, '', 'too-short'] as $token) {
            config(['services.youtube_cookie_sync.token' => $token]);

            $this->sync($this->cookies(), 'too-short')->assertNotFound();
        }

        $this->assertNull(StorageSetting::firstOrFail()->youtube_cookies);
    }

    public function test_a_missing_or_wrong_token_is_refused_and_nothing_is_stored(): void
    {
        $this->sync($this->cookies(), null)->assertUnauthorized();
        $this->sync($this->cookies(), 'not-the-token')->assertUnauthorized();
        $this->sync($this->cookies(), self::TOKEN.'x')->assertUnauthorized();

        $this->assertNull(StorageSetting::firstOrFail()->youtube_cookies);
    }

    public function test_the_cookies_are_saved_encrypted_and_only_youtube_and_google_ones_are_kept(): void
    {
        $response = $this->sync($this->cookies())->assertOk()->assertExactJson(['cookies' => 3, 'retried' => 0]);

        $stored = StorageSetting::firstOrFail()->youtube_cookies;
        $this->assertStringStartsWith("# Netscape HTTP Cookie File\n", $stored);
        $this->assertStringContainsString("\n.youtube.com\tTRUE\t/\tTRUE\t2147483647\tSID\tfirst-sid-value\n", $stored);
        $this->assertStringContainsString("\n#HttpOnly_.youtube.com\tTRUE\t/\tTRUE\t2147483647\t__Secure-3PSID\tpsid-secret-value\n", $stored);
        $this->assertStringContainsString("\n#HttpOnly_.google.com\tTRUE\t/\tTRUE\t0\tLOGIN_INFO\tlogin-info\n", $stored);
        $this->assertStringNotContainsString('other-site-secret', $stored);
        $this->assertStringNotContainsString('facebook', $stored);

        // Encrypted at rest, and never sent back.
        $raw = (string) DB::table('storage_settings')->value('youtube_cookies');
        $this->assertStringNotContainsString('first-sid-value', $raw);
        $this->assertStringNotContainsString('first-sid-value', $response->getContent());
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_a_new_sync_replaces_the_old_cookies_and_retries_failed_youtube_fetches(): void
    {
        $category = Category::where('slug', 'podcast')->firstOrFail()->id;
        $failed = MediaContent::create([
            'category_id' => $category, 'title' => 'Failed', 'slug' => 'failed', 'media_type' => 'audio',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'processing_status' => 'failed', 'processing_error' => 'Sign in to confirm you are not a bot',
        ]);
        $upload = MediaContent::create([
            'category_id' => $category, 'title' => 'Upload', 'slug' => 'upload', 'media_type' => 'audio',
            'source_path' => 'media-contents/uploads/a.mp3', 'processing_status' => 'failed', 'processing_error' => 'x',
        ]);

        $this->sync($this->cookies())->assertOk()->assertJsonPath('retried', 1);

        $this->assertSame('processing', $failed->fresh()->processing_status);
        $this->assertSame('failed', $upload->fresh()->processing_status);
        Queue::assertPushed(FetchYoutubeAudio::class, fn (FetchYoutubeAudio $job) => $job->mediaContentId === $failed->id);

        $newer = $this->cookies();
        $newer[0]['value'] = 'newer-sid-value';
        $this->sync($newer)->assertOk()->assertJsonPath('retried', 0);

        $stored = StorageSetting::firstOrFail()->youtube_cookies;
        $this->assertStringContainsString('newer-sid-value', $stored);
        $this->assertStringNotContainsString('first-sid-value', $stored);
    }

    public function test_a_request_without_youtube_cookies_is_refused_and_the_saved_ones_stay(): void
    {
        StorageSetting::firstOrFail()->update(['youtube_cookies' => "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t1\tSID\tkeep-me\n"]);

        $this->sync([['domain' => '.facebook.com', 'name' => 'c_user', 'value' => 'x', 'path' => '/']])->assertStatus(422);
        $this->sync([])->assertStatus(422);
        $this->call('POST', self::URL, [], [], [], ['HTTP_X-COOKIE-SYNC-TOKEN' => self::TOKEN, 'CONTENT_TYPE' => 'application/json'], 'not json at all')->assertStatus(422);

        $this->assertStringContainsString('keep-me', StorageSetting::firstOrFail()->youtube_cookies);
    }

    public function test_an_oversized_request_is_refused(): void
    {
        $cookies = $this->cookies();
        $cookies[0]['value'] = str_repeat('a', 1_100_000);

        $this->sync($cookies)->assertStatus(413);
        $this->assertNull(StorageSetting::firstOrFail()->youtube_cookies);
    }
}
