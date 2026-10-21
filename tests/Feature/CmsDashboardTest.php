<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_the_top_bar_counts_everyone_online_beside_the_bell_on_every_page(): void
    {
        $admin = $this->createAdmin([]);
        $customer = Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
        $idleCustomer = Customer::create(['first_name' => 'Old', 'last_name' => 'Timer', 'email' => 'old@example.com', 'password' => Hash::make('password')]);
        $customer->createToken('app')->accessToken->forceFill(['last_used_at' => now()->subMinute()])->save();
        $idleCustomer->createToken('app')->accessToken->forceFill(['last_used_at' => now()->subHours(2)])->save();
        DB::table('sessions')->insert([
            'id' => 'qa-session', 'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'phpunit',
            'payload' => base64_encode('irrelevant'), 'last_activity' => now()->subMinute()->timestamp,
        ]);
        $this->actingAs($admin, 'admin');

        // The same number as the dashboard's "All users online" card (a session and a token in the last five minutes, the
        // idle token not counted), in the top bar of any page, just before the bell; it links to that card.
        foreach (['/admin', '/admin/profile', '/admin/notifications'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<a href="'.route('admin.dashboard').'" class="online-count" title="All users online" aria-label="All users online: 2"><span class="online-dot" aria-hidden="true"></span><b>2</b><span class="online-label">Online</span></a>', $html, $url);
            $this->assertLessThan(strpos($html, 'class="icon-button notification"'), strpos($html, 'class="online-count"'), "{$url}: before the bell");
        }
        $this->get('/admin')->assertSeeInOrder(['All users online', '2']);
    }

    public function test_users_online_lists_recently_active_people_with_their_email(): void
    {
        $admin = $this->createAdmin([]);
        $customer = Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
        $idle = Customer::create(['first_name' => 'Old', 'last_name' => 'Timer', 'email' => 'old@example.com', 'password' => Hash::make('password')]);

        $customer->createToken('app')->accessToken->forceFill(['last_used_at' => now()->subMinute()])->save();
        $idle->createToken('app')->accessToken->forceFill(['last_used_at' => now()->subHours(2)])->save();

        $this->actingAs($admin, 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Users online')
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com')
            ->assertDontSee('Old Timer');
    }

    public function test_users_online_shows_an_uploaded_profile_picture_by_its_full_url(): void
    {
        $customer = Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password'), 'avatar_url' => 'avatars/jane.jpg']);
        $customer->createToken('app')->accessToken->forceFill(['last_used_at' => now()->subMinute()])->save();

        $this->actingAs($this->createAdmin([]), 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('avatars/jane.jpg').'"', false);
    }

    public function test_visitors_by_country_passes_the_per_country_counts_to_the_map(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        foreach ([['a', 'KH'], ['b', 'KH'], ['c', 'US'], ['d', null]] as [$name, $country]) {
            Customer::create(['first_name' => $name, 'last_name' => 'User', 'email' => "{$name}@example.com", 'password' => Hash::make('password')]);
            DB::table('users')->where('email', "{$name}@example.com")->update(['country' => $country]);
        }

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Visitors by country')
            ->assertSee('3 of 5 users have a known country')
            ->assertSee('data-country-map', false)
            ->assertSee('{&quot;KH&quot;:2,&quot;US&quot;:1}', false);
    }

    public function test_visitors_by_country_explains_itself_when_no_country_is_known_yet(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('No location data yet')
            ->assertDontSee('data-country-map', false);
    }

    public function test_podcasts_table_shows_episode_count_and_total_plays(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');
        $program = Program::create([
            'category_id' => Category::where('slug', 'podcast')->firstOrFail()->id,
            'name' => 'Morning Brief',
            'slug' => 'morning-brief',
            'is_published' => true,
        ]);
        foreach ([40, 2] as $i => $plays) {
            MediaContent::create([
                'category_id' => Category::where('slug', 'podcast')->firstOrFail()->id,
                'program_id' => $program->id,
                'title' => "Episode {$i}",
                'slug' => "episode-{$i}",
                'media_type' => 'audio',
                'play_count' => $plays,
            ]);
        }

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Morning Brief', 'Published', '<b class="dash-num">2</b>', '<b class="dash-num">42</b>'], false);
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

    public function test_it_shows_an_audio_card_below_the_recent_podcasts(): void
    {
        $this->actingAs($this->createAdmin(['view-media-content']), 'admin');
        $audio = fn (string $slug, string $title, array $extra = []) => MediaContent::create([
            'category_id' => Category::where('slug', $slug)->firstOrFail()->id,
            'title' => $title, 'slug' => str($title)->slug()->toString(), 'media_type' => 'audio', ...$extra,
        ]);
        $audio('news', 'Market Update', ['is_published' => true, 'play_count' => 7]);
        $audio('health', 'Sleep Well');
        $audio('song', 'Just A Song');

        $response = $this->get('/admin')->assertOk();
        $html = $response->getContent();

        // Under the podcasts card: News, Health and Economy/Finance items, newest first, with their status and plays.
        $response->assertSeeInOrder(['Recent podcasts', 'Recent audio', 'Sleep Well', 'Draft', 'Market Update', 'Published', '<b class="dash-num">7</b>'], false);
        $card = substr($html, $start = strpos($html, 'Recent audio'), strpos($html, '</section>', $start) - $start);
        $this->assertStringContainsString('<small>News</small>', $card);
        $this->assertStringContainsString('<small>Health</small>', $card);
        $this->assertStringNotContainsString('Just A Song', $card);
        $this->assertStringContainsString('href="'.e(route('admin.music.index', ['category' => 'audio'])).'"', $card);
    }

    public function test_the_audio_card_says_so_when_there_is_no_audio_yet(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');

        $this->get('/admin')->assertOk()->assertSee('Recent audio')->assertSee('No audio yet.');
    }

    public function test_it_shows_the_top_5_most_played_songs_below_recent_radio_channels(): void
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

        // Top 5 by play count (Track 12 down to Track 8); everything below doesn't make the cut.
        $this->assertStringContainsString('Track 12', $html);
        $this->assertStringContainsString('Track 8', $html);
        $this->assertStringNotContainsString('Track 7<', $html);
        $this->assertStringNotContainsString('Track 1<', $html);

        // Below "Recent radio channels" means the 3rd panel in the dashboard grid.
        $firstPanel = strpos($html, 'Recent radio channels');
        $secondPanel = strpos($html, 'Most played music');
        $this->assertGreaterThan($firstPanel, $secondPanel);
    }

    public function test_it_cuts_long_song_titles_to_25_characters_on_the_most_played_panel(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');

        $title = str_repeat('a', 40);
        MediaContent::create([
            'category_id' => Category::where('slug', 'song')->firstOrFail()->id,
            'title' => $title,
            'slug' => 'a-very-long-title',
            'media_type' => 'audio',
            'play_count' => 1,
        ]);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('>'.str_repeat('a', 25).'...</b>', $html);
        $this->assertStringNotContainsString('>'.$title.'</b>', $html);
    }
}
