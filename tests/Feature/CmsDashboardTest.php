<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\CmsAdmin\Models\User as Customer;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CmsDashboardTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_users_counts_admins_and_customers_together(): void
    {
        $admin = $this->createAdmin([]);
        Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
        Customer::create(['first_name' => 'Sam', 'last_name' => 'Lee', 'email' => 'sam@example.com', 'password' => Hash::make('password')]);
        $this->actingAs($admin, 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('All users')
            ->assertSeeInOrder(['All users', '3']);
    }

    public function test_all_users_online_counts_a_recent_session_and_a_recently_used_token_once_each(): void
    {
        $admin = $this->createAdmin([]);
        $customer = Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
        $idleCustomer = Customer::create(['first_name' => 'Old', 'last_name' => 'Timer', 'email' => 'old@example.com', 'password' => Hash::make('password')]);

        $token = $customer->createToken('app');
        $token->accessToken->forceFill(['last_used_at' => now()->subMinute()])->save();

        $idleToken = $idleCustomer->createToken('app');
        $idleToken->accessToken->forceFill(['last_used_at' => now()->subHours(2)])->save();

        DB::table('sessions')->insert([
            'id' => 'qa-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('irrelevant'),
            'last_activity' => now()->subMinute()->timestamp,
        ]);

        $this->actingAs($admin, 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['All users online', '2']);
    }

    public function test_it_shows_recent_podcasts_instead_of_recent_admin_users(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        Program::create([
            'category_id' => Category::where('slug', 'podcast')->firstOrFail()->id,
            'name' => 'Morning Brief',
            'slug' => 'morning-brief',
            'is_published' => true,
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Recent podcasts')
            ->assertSee('Morning Brief')
            ->assertDontSee('Recent admin users');
    }

    public function test_it_shows_the_top_10_most_played_songs_below_recent_radio_channels(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        $songCategoryId = Category::where('slug', 'song')->firstOrFail()->id;

        for ($i = 1; $i <= 12; $i++) {
            MediaContent::create([
                'category_id' => $songCategoryId,
                'title' => "Track {$i}",
                'slug' => "track-{$i}",
                'media_type' => 'audio',
                'play_count' => $i,
            ]);
        }

        $html = $this->get('/admin')->assertOk()->assertSee('Most played music')->getContent();

        // Top 10 by play count (Track 12 down to Track 3); the two least-played don't make the cut.
        $this->assertStringContainsString('Track 12', $html);
        $this->assertStringContainsString('Track 3', $html);
        $this->assertStringNotContainsString('Track 2<', $html);
        $this->assertStringNotContainsString('Track 1<', $html);

        // Below "Recent radio channels" means the 3rd panel in the dashboard grid.
        $firstPanel = strpos($html, 'Recent radio channels');
        $secondPanel = strpos($html, 'Most played music');
        $this->assertGreaterThan($firstPanel, $secondPanel);
    }
}
