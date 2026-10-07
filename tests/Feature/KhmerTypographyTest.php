<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class KhmerTypographyTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_khmer_pages_get_the_khmer_font_and_no_letter_spacing(): void
    {
        $admin = $this->createAdmin([]);
        $admin->update(['locale' => 'km']);
        $this->actingAs($admin->refresh(), 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('"Khmer OS Battambang"', false)
            // Tracking pulls a Khmer cluster apart, so it is switched off for everything.
            ->assertSee('letter-spacing: normal !important', false)
            ->assertSee('html[lang="km"] .data-table th', false);
    }

    public function test_english_pages_keep_their_letter_spacing(): void
    {
        $admin = $this->createAdmin([]);
        $admin->update(['locale' => 'en']);
        $this->actingAs($admin->refresh(), 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('letter-spacing: normal !important', false)
            ->assertDontSee('Khmer OS Battambang', false);
    }
}
