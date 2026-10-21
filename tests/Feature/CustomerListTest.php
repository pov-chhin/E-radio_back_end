<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Src\CmsAdmin\Models\User as Customer;
use Src\Social\Models\Bookmark;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CustomerListTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    public function test_it_lists_customers(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $this->customer();

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('janedoe')
            ->assertSee('jane@example.com');
    }

    public function test_admin_accounts_do_not_appear_in_the_customer_list(): void
    {
        $admin = $this->createAdmin(['view-customers']);
        $this->actingAs($admin, 'admin');
        $this->customer(['email' => 'listener@example.com']);

        $html = $this->get('/admin/customers')->assertOk()->getContent();
        $rows = substr($html, strpos($html, 'data-table-wrap'), strpos($html, '</table>') - strpos($html, 'data-table-wrap'));

        $this->assertStringContainsString('listener@example.com', $rows);
        $this->assertStringNotContainsString($admin->email, $rows);
    }

    public function test_it_shows_a_bookmark_count(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $customer = $this->customer();
        Bookmark::create(['user_id' => $customer->id, 'bookmarkable_type' => 'station', 'bookmarkable_id' => 1]);
        Bookmark::create(['user_id' => $customer->id, 'bookmarkable_type' => 'station', 'bookmarkable_id' => 2]);

        $this->get('/admin/customers')->assertOk()->assertSeeInOrder(['Jane Doe', '2']);
    }

    public function test_it_shows_an_uploaded_profile_picture(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $this->customer(['avatar_url' => 'avatars/jane.jpg']);

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('<img src="'.Storage::disk('public')->url('avatars/jane.jpg').'"', false);
    }

    public function test_it_shows_an_external_profile_picture_as_it_is(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $this->customer(['avatar_url' => 'https://lh3.googleusercontent.com/a/jane']);

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('<img src="https://lh3.googleusercontent.com/a/jane"', false);
    }

    public function test_a_customer_without_a_picture_gets_their_initial(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $this->customer();

        $this->get('/admin/customers')
            ->assertOk()
            ->assertSee('class="cust-avatar">J</span>', false)
            ->assertDontSee('<img', false);
    }

    public function test_the_customer_list_is_a_datatable_with_a_strip_of_filters(): void
    {
        $this->actingAs($this->createAdmin(['view-customers']), 'admin');
        $jane = $this->customer();
        Bookmark::create(['user_id' => $jane->id, 'bookmarkable_type' => 'station', 'bookmarkable_id' => 1]);
        $this->customer(['first_name' => 'Sam', 'last_name' => 'Roe', 'username' => null, 'email' => 'sam@example.com']);

        $html = $this->get('/admin/customers')->assertOk()->getContent();

        // Every customer is on the page (no server paging): the table searches, sorts, filters and pages them.
        $this->assertStringContainsString('<table class="data-table" id="customer-table">', $html);
        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringContainsString("jQuery('#customer-table').DataTable(", $html);
        $this->assertStringContainsString("emptyTable: 'No customers yet.'", $html);
        $this->assertStringContainsString("attachTableFilters(table, { filtersId: 'customer-filters', searchLabel: 'Search' });", $html);

        // The strip: Bookmarks and Username (with / none), and Clear. No date boxes.
        $this->assertMatchesRegularExpression('/<div class="table-filters" id="customer-filters"[^>]*>\s*<label class="filter-field"><span>Bookmarks<\/span>\s*<span class="filter-control"><i class="fa fa-bookmark-o" aria-hidden="true"><\/i>\s*<select data-filter="bookmarks">\s*<option value="">All<\/option>\s*<option value="with">With bookmarks<\/option>\s*<option value="none">No bookmarks<\/option>\s*<\/select>/', $html);
        $this->assertMatchesRegularExpression('/<label class="filter-field"><span>Username<\/span>\s*<span class="filter-control"><i class="fa fa-at" aria-hidden="true"><\/i>\s*<select data-filter="username">\s*<option value="">All<\/option>\s*<option value="with">With username<\/option>\s*<option value="none">No username<\/option>\s*<\/select>/', $html);
        $this->assertMatchesRegularExpression('/data-filter-clear title="Clear filters" aria-label="Clear filters" hidden>/', $html);
        $this->assertStringNotContainsString('data-filter="from"', $html);

        // Each row says whether the customer has bookmarks and a username; the count and the join date sort as numbers.
        $this->assertStringContainsString('<tr data-bookmarks="with" data-username="with">', $html);
        $this->assertStringContainsString('<tr data-bookmarks="none" data-username="none">', $html);
        $this->assertMatchesRegularExpression('/data-label="Bookmarks" data-order="1"/', $html);
        $this->assertMatchesRegularExpression('/data-label="Joined" data-order="\d{9,}"/', $html);
        $this->assertStringContainsString('data-order="Jane Doe"', $html);

        // Responsive: the value cells name their column for the card layout on phones, and the shared styles are in.
        foreach (['Username', 'Email', 'Joined', 'Bookmarks'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html, $label);
        }
        $this->assertStringContainsString('@media (max-width: 760px)', $html);
    }

    public function test_it_requires_the_view_customers_permission(): void
    {
        $this->actingAs($this->createAdmin([]), 'admin');

        $this->get('/admin/customers')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/customers')->assertRedirect(route('login'));
    }
}
