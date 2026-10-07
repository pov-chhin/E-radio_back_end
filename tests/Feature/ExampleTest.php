<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }

    public function test_the_old_studio_address_still_leads_to_the_admin(): void
    {
        $this->get('/admin/studio')->assertRedirect('/admin');
    }

    public function test_the_studio_pages_are_gone(): void
    {
        foreach (['live', 'stations', 'player', 'playlist', 'schedule', 'listeners', 'settings'] as $page) {
            $this->get("/admin/studio/{$page}")->assertNotFound();
        }
    }
}
