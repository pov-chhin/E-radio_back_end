<?php

namespace Tests\Feature;

use App\Support\AdminCache;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use Predis\Connection\Resource\Exception\StreamInitException;
use Predis\PredisException;
use RuntimeException;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\Program;
use Src\Setting\Models\RadioScraperSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class AdminCacheTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function newPodcast(string $name): Program
    {
        return Program::create([
            'category_id' => Category::where('slug', 'podcast')->firstOrFail()->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_published' => true,
        ]);
    }

    /**
     * The two ways predis fails with Redis down. A refused connection is a StreamInitException — found by running
     * the client against a closed port; it is not a ConnectionException, which is what a dropped one throws.
     *
     * @return array<string, array{Closure(): PredisException}>
     */
    public static function redisFailures(): array
    {
        return [
            'connection refused' => [fn () => new StreamInitException('No connection could be made [tcp://127.0.0.1:6379]')],
            'connection dropped' => [fn () => new ConnectionException(Mockery::mock(NodeConnectionInterface::class), 'Connection reset by peer')],
        ];
    }

    public function test_a_hit_returns_the_cached_value_without_running_the_callback_again(): void
    {
        $calls = 0;
        $read = function () use (&$calls) {
            $calls++;

            return 'from mysql';
        };

        $this->assertSame('from mysql', AdminCache::remember('probe', 60, $read));
        $this->assertSame('from mysql', AdminCache::remember('probe', 60, $read));

        $this->assertSame(1, $calls);
        $this->assertSame('from mysql', Cache::get('admin:probe'));
    }

    public function test_the_value_is_read_again_once_its_time_is_up(): void
    {
        $calls = 0;
        $read = function () use (&$calls) {
            return ++$calls;
        };

        $this->assertSame(1, AdminCache::remember('probe', 60, $read));

        $this->travel(61)->seconds();

        $this->assertSame(2, AdminCache::remember('probe', 60, $read));
    }

    #[DataProvider('redisFailures')]
    public function test_an_unreachable_redis_falls_back_to_the_database_read(Closure $failure): void
    {
        Cache::shouldReceive('remember')->once()->andThrow($failure());

        $this->assertSame('from mysql', AdminCache::remember('probe', 60, fn () => 'from mysql'));
    }

    public function test_an_error_inside_the_callback_is_not_swallowed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bad query');

        AdminCache::remember('probe', 60, fn () => throw new RuntimeException('bad query'));
    }

    public function test_the_dashboard_serves_its_lists_from_the_cache_within_the_minute(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        $this->newPodcast('Morning Brief');

        $this->get('/admin')->assertOk()->assertSee('Morning Brief');

        // Added after the dashboard was cached: not shown yet…
        $this->newPodcast('Evening Wrap');
        $this->get('/admin')->assertOk()->assertSee('Morning Brief')->assertDontSee('Evening Wrap');

        // …but it is once the minute is up.
        $this->travel(61)->seconds();
        $this->get('/admin')->assertOk()->assertSee('Evening Wrap');
    }

    #[DataProvider('redisFailures')]
    public function test_the_dashboard_still_loads_when_redis_is_down(Closure $failure): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        $this->newPodcast('Morning Brief');

        Cache::shouldReceive('remember')->andThrow($failure());

        $this->get('/admin')->assertOk()->assertSee('Morning Brief');
    }

    public function test_the_scraper_setting_on_the_dashboard_is_never_stale(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        $setting = RadioScraperSetting::firstOrFail();

        $setting->update(['enabled' => false]);
        $this->get('/admin')->assertOk()->assertSee('<strong class="dash-stat-value">Disabled</strong>', false);

        $setting->update(['enabled' => true]);
        $this->get('/admin')->assertOk()->assertSee('<strong class="dash-stat-value">Enabled</strong>', false);
    }

    public function test_users_online_refreshes_faster_than_the_rest_of_the_dashboard(): void
    {
        $admin = $this->createAdmin([]);
        $this->actingAs($admin, 'admin');

        $this->assertSame(0, $this->usersOnlineShown());

        DB::table('sessions')->insert([
            'id' => 'qa-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('irrelevant'),
            'last_activity' => now()->timestamp,
        ]);

        // Still inside the 15s online window: the cached zero is served.
        $this->assertSame(0, $this->usersOnlineShown());

        // 16 seconds on: past the online window, well inside the 60s one.
        $this->travel(16)->seconds();

        $this->assertSame(1, $this->usersOnlineShown());
    }

    /** The number on the "All users online" card (not the "All users" one beside it). */
    private function usersOnlineShown(): int
    {
        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#All users online</span>\s*<strong class="dash-stat-value">([\d,]+)</strong>#', $html, $m));

        return (int) str_replace(',', '', $m[1]);
    }
}
