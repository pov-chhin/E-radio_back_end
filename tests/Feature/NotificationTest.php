<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Src\Catalog\Models\Category;
use Src\User\User;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    public function test_syncing_radio_channels_notifies_the_acting_admin(): void
    {
        $admin = $this->createAdmin(['create-radio-channels']);
        $this->actingAs($admin, 'admin');

        Http::fake(['de1.api.radio-browser.info/*' => Http::response([
            ['stationuuid' => 'aaa', 'name' => 'Pulse FM', 'url_resolved' => 'https://stream.example.com/pulse', 'bitrate' => 128, 'votes' => 10, 'tags' => 'pop', 'lastcheckok' => 1],
        ])]);

        $this->post('/admin/radio-channels/sync')->assertRedirect();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('Radio channels synced', $admin->notifications()->first()->data['title']);
    }

    public function test_toggling_live_notifies_the_acting_admin(): void
    {
        $admin = $this->createAdmin(['view-dashboard']);
        $this->actingAs($admin, 'admin');

        $this->post('/admin/studio/live/toggle')->assertRedirect();

        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_publishing_media_content_notifies_the_acting_admin(): void
    {
        $admin = $this->createAdmin(['create-media-content']);
        $this->actingAs($admin, 'admin');
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track',
            'slug' => 'qa-track',
            'media_type' => 'audio',
            'status' => 'public',
        ])->assertRedirect();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('Media content published', $admin->notifications()->first()->data['title']);
    }

    public function test_creating_a_draft_media_content_does_not_notify(): void
    {
        $admin = $this->createAdmin(['create-media-content']);
        $this->actingAs($admin, 'admin');
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);

        $this->post('/admin/media-contents', [
            'category_id' => $category->id,
            'title' => 'QA Track',
            'slug' => 'qa-track',
            'media_type' => 'audio',
        ])->assertRedirect();

        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_editing_already_published_media_content_does_not_notify_again(): void
    {
        $admin = $this->createAdmin(['create-media-content', 'edit-media-content']);
        $this->actingAs($admin, 'admin');
        $category = Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);

        $this->post('/admin/media-contents', [
            'category_id' => $category->id, 'title' => 'QA Track', 'slug' => 'qa-track',
            'media_type' => 'audio', 'status' => 'public',
        ]);
        $this->assertSame(1, $admin->notifications()->count());

        $mediaContent = \Src\Catalog\Models\MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $category->id, 'title' => 'QA Track Updated', 'slug' => 'qa-track',
            'media_type' => 'audio', 'status' => 'public',
        ])->assertRedirect();

        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_registering_an_admin_notifies_other_admins_with_manage_users_but_not_the_actor_or_the_new_user(): void
    {
        // CreatesAdminUser reuses one shared "test-admin" role per test, so an
        // admin created with a *different* permission list would still end up
        // with view-users through that shared role. Use createAdmin([]) —
        // which skips role assignment entirely — to get a genuinely unrelated admin.
        $actor = $this->createAdmin(['create-users']);
        $teammate = $this->createAdmin(['create-users', 'view-users']);
        $unrelatedAdmin = $this->createAdmin([]);
        $this->actingAs($actor, 'admin');

        $this->post('/admin/users', [
            'name' => 'New Admin', 'email' => 'new-admin@example.com', 'password' => 'password123',
        ])->assertRedirect();

        $newUser = User::where('email', 'new-admin@example.com')->firstOrFail();

        $this->assertSame(0, $actor->notifications()->count());
        $this->assertSame(1, $teammate->notifications()->count());
        $this->assertSame(0, $unrelatedAdmin->notifications()->count());
        $this->assertSame(0, $newUser->notifications()->count());
    }

    public function test_notifications_index_lists_and_marks_as_read(): void
    {
        $admin = $this->createAdmin(['view-dashboard']);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/studio/live/toggle');
        $notification = $admin->notifications()->firstOrFail();

        $this->get('/admin/notifications')->assertOk()->assertSee($notification->data['title']);

        $this->post("/admin/notifications/{$notification->id}/read")->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        $admin = $this->createAdmin(['view-dashboard']);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/studio/live/toggle');
        $this->post('/admin/studio/live/toggle');

        $this->assertSame(2, $admin->unreadNotifications()->count());

        $this->post('/admin/notifications/read-all')->assertRedirect();

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_an_admin_cannot_mark_another_admins_notification_as_read(): void
    {
        $owner = $this->createAdmin(['view-dashboard']);
        $intruder = $this->createAdmin(['view-dashboard']);
        $this->actingAs($owner, 'admin')->post('/admin/studio/live/toggle');
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder, 'admin')
            ->post("/admin/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_deleting_a_notification_removes_it(): void
    {
        $admin = $this->createAdmin(['view-dashboard']);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/studio/live/toggle');
        $notification = $admin->notifications()->firstOrFail();

        $this->delete("/admin/notifications/{$notification->id}")->assertRedirect();

        $this->assertSame(0, $admin->notifications()->count());
    }
}
