<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Src\Setting\Models\LanguageSetting;
use Src\User\User;
use Tests\TestCase;

/** Every admin page that can be opened without picking a record must render in Khmer without an error. */
class KhmerPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Some admin pages call out to other servers; a failing one must not be what this test measures.
        Http::fake(['*' => Http::response([], 500)]);
        $this->seed();
        LanguageSetting::firstOrFail()->update(['locale' => 'km']);
        $this->actingAs(User::firstOrFail(), 'admin');
    }

    /** @return list<string> */
    private function adminPages(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true)
                && str_starts_with($route->uri(), 'admin')
                && ! str_contains($route->uri(), '{'))
            ->map(fn ($route) => '/'.$route->uri())
            ->unique()
            ->values()
            ->all();
    }

    public function test_every_admin_page_renders_in_khmer(): void
    {
        $pages = $this->adminPages();
        $this->assertGreaterThan(10, count($pages), 'The route scan found too few admin pages.');

        $rendered = 0;
        foreach ($pages as $page) {
            $response = $this->get($page);

            if ($response->isRedirect()) {
                continue;
            }

            $response->assertOk();
            $this->assertStringContainsString('lang="km"', $response->getContent(), "{$page} did not render in Khmer");
            $rendered++;
        }

        $this->assertGreaterThan(8, $rendered, 'Too few admin pages rendered to be a meaningful check.');
    }

    public function test_the_guest_login_page_follows_the_site_default_language(): void
    {
        auth('admin')->logout();

        $this->get('/login')->assertOk()->assertSee('lang="km"', false)->assertSee('សូមស្វាគមន៍');
    }
}
