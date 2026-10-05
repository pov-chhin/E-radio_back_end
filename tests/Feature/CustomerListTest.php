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
