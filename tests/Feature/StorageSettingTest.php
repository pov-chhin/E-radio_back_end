<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Setting\Models\StorageSetting;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class StorageSettingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs($this->createAdmin(['manage-settings', 'view-dashboard']), 'admin');
    }

    public function test_migration_seeds_a_singleton_row_from_config_defaults(): void
    {
        $this->assertSame(1, StorageSetting::count());
        $setting = StorageSetting::firstOrFail();
        $this->assertSame('media-contents/thumbnails', $setting->image_directory);
        $this->assertSame('media-contents/streams', $setting->audio_directory);
    }

    public function test_edit_page_shows_both_tabs(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Radio Scraper')
            ->assertSee('Storage')
            ->assertSee('Image folder')
            ->assertSee('Audio folder');
    }

    public function test_update_saves_and_applies_to_config(): void
    {
        $this->put('/admin/settings/storage', [
            'image_directory' => 'uploads/images',
            'audio_directory' => 'uploads/audio',
        ])->assertRedirect();

        $setting = StorageSetting::firstOrFail();
        $this->assertSame('uploads/images', $setting->image_directory);
        $this->assertSame('uploads/audio', $setting->audio_directory);

        $this->assertSame('uploads/images', config('services.media_storage.image_directory'));
        $this->assertSame('uploads/audio', config('services.media_storage.audio_directory'));
    }

    public function test_rejects_a_directory_that_tries_to_escape_the_disk_root(): void
    {
        $this->put('/admin/settings/storage', [
            'image_directory' => '../../etc',
            'audio_directory' => 'media-contents/streams',
        ])->assertSessionHasErrors('image_directory');

        $this->assertSame('media-contents/thumbnails', StorageSetting::firstOrFail()->image_directory);
    }
}
