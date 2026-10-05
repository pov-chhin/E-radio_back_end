<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Src\CmsAdmin\Models\User as Customer;
use Tests\TestCase;

class RecordUserCountryTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
    }

    private function countryOf(Customer $customer): ?string
    {
        return DB::table('users')->where('id', $customer->id)->value('country');
    }

    public function test_it_records_the_country_from_the_proxy_header(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer);

        $this->getJson('/api/me', ['CF-IPCountry' => 'kh'])->assertOk();

        $this->assertSame('KH', $this->countryOf($customer));
    }

    public function test_it_updates_the_country_when_the_user_connects_from_somewhere_else(): void
    {
        $customer = $this->customer();
        DB::table('users')->where('id', $customer->id)->update(['country' => 'KH']);
        Sanctum::actingAs($customer->fresh());

        $this->getJson('/api/me', ['CF-IPCountry' => 'US'])->assertOk();

        $this->assertSame('US', $this->countryOf($customer));
    }

    public function test_it_ignores_unknown_placeholder_and_malformed_values(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer);

        foreach (['XX', 'T1', 'KHM', '', '1A'] as $value) {
            $this->getJson('/api/me', ['CF-IPCountry' => $value])->assertOk();
        }

        $this->assertNull($this->countryOf($customer));
    }

    public function test_it_leaves_the_country_alone_when_no_header_is_present(): void
    {
        $customer = $this->customer();
        DB::table('users')->where('id', $customer->id)->update(['country' => 'KH']);
        Sanctum::actingAs($customer->fresh());

        $this->getJson('/api/me')->assertOk();

        $this->assertSame('KH', $this->countryOf($customer));
    }
}
