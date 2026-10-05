<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\CmsAdmin\Models\User as Customer;
use Tests\TestCase;

class RecordUserActivityTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => Hash::make('password')]);
    }

    private function lastUsedAt(Customer $customer): ?string
    {
        return DB::table('personal_access_tokens')->where('tokenable_id', $customer->id)->value('last_used_at');
    }

    public function test_a_public_request_with_a_token_marks_the_user_as_active(): void
    {
        $customer = $this->customer();
        $token = $customer->createToken('mobile')->plainTextToken;
        $this->assertNull($this->lastUsedAt($customer));

        $this->getJson('/api/stations', ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertNotNull($this->lastUsedAt($customer));
    }

    public function test_a_public_request_without_a_token_changes_nothing(): void
    {
        $customer = $this->customer();
        $customer->createToken('mobile');

        $this->getJson('/api/stations')->assertOk();

        $this->assertNull($this->lastUsedAt($customer));
    }

    public function test_a_bad_token_on_a_public_request_is_ignored(): void
    {
        $this->customer();

        $this->getJson('/api/stations', ['Authorization' => 'Bearer 1|not-a-real-token'])->assertOk();
    }
}
