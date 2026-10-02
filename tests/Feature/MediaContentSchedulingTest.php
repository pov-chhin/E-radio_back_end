<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\User\User;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class MediaContentSchedulingTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = $this->createAdmin(['view-media-content', 'create-media-content', 'edit-media-content', 'delete-media-content']);
        $this->actingAs($this->admin, 'admin');
    }

    private function category(): Category
    {
        return Category::create(['name' => 'QA Category', 'slug' => 'qa-category']);
    }

    public function test_public_status_with_no_date_goes_live_immediately(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'public',
        ])->assertRedirect();

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame('public', $mediaContent->status);
        $this->assertTrue($mediaContent->published_at->isPast() || $mediaContent->published_at->equalTo(now()));
    }

    public function test_scheduled_status_with_a_future_date_is_scheduled_not_public(): void
    {
        $future = now()->addDays(3);

        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => $future->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame('scheduled', $mediaContent->status);
        $this->assertTrue($mediaContent->published_at->isFuture());
    }

    public function test_scheduled_publish_time_is_interpreted_in_the_admins_local_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        try {
            $this->post('/admin/media-contents', [
                'category_id' => $this->category()->id,
                'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
                'status' => 'scheduled',
                'published_at' => '2026-01-02T09:00', // 9am, meant as the admin's own local time
            ])->assertRedirect();

            $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
            $this->assertSame('Asia/Phnom_Penh', config('app.admin_timezone'));
            // Asia/Phnom_Penh is UTC+7 year-round (no DST), so 9am local is 2am UTC.
            $this->assertSame('2026-01-02 02:00:00', $mediaContent->published_at->toDateTimeString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_scheduled_status_without_a_date_fails_validation(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
        ])->assertSessionHasErrors('published_at');

        $this->assertNull(MediaContent::where('slug', 'qa-track')->first());
    }

    public function test_scheduled_status_with_a_past_date_fails_validation(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('published_at');

        $this->assertNull(MediaContent::where('slug', 'qa-track')->first());
    }

    public function test_private_status_leaves_it_unpublished_with_no_publish_date(): void
    {
        $this->post('/admin/media-contents', [
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'private',
        ])->assertRedirect();

        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame('private', $mediaContent->status);
        $this->assertNull($mediaContent->published_at);
    }

    public function test_scheduling_does_not_notify_but_going_public_does(): void
    {
        $admin = $this->admin;
        $categoryId = $this->category()->id;

        $this->post('/admin/media-contents', [
            'category_id' => $categoryId,
            'title' => 'Scheduled Track', 'slug' => 'scheduled-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]);
        $this->assertSame(0, $admin->notifications()->count());

        $this->post('/admin/media-contents', [
            'category_id' => $categoryId,
            'title' => 'Live Track', 'slug' => 'live-track', 'media_type' => 'audio',
            'status' => 'public',
        ]);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_a_scheduled_item_becomes_public_once_its_date_passes_with_no_further_action(): void
    {
        $mediaContent = MediaContent::create([
            'category_id' => $this->category()->id,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'is_published' => true, 'published_at' => now()->addDay(),
        ]);
        $this->assertSame('scheduled', $mediaContent->status);

        Carbon::setTestNow(now()->addDays(2));
        try {
            $this->assertSame('public', $mediaContent->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rescheduling_an_already_scheduled_item_further_out_does_not_notify(): void
    {
        $admin = $this->admin;
        $categoryId = $this->category()->id;
        $this->post('/admin/media-contents', [
            'category_id' => $categoryId,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $categoryId,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertSame('scheduled', $mediaContent->fresh()->status);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_switching_a_scheduled_item_to_public_makes_it_live_immediately_and_notifies_once(): void
    {
        $admin = $this->admin;
        $categoryId = $this->category()->id;
        $this->post('/admin/media-contents', [
            'category_id' => $categoryId,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'scheduled',
            'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $mediaContent = MediaContent::where('slug', 'qa-track')->firstOrFail();
        $this->assertSame(0, $admin->notifications()->count());

        $this->put("/admin/media-contents/{$mediaContent->id}", [
            'category_id' => $categoryId,
            'title' => 'QA Track', 'slug' => 'qa-track', 'media_type' => 'audio',
            'status' => 'public',
        ])->assertRedirect();

        $mediaContent->refresh();
        $this->assertSame('public', $mediaContent->status);
        $this->assertTrue($mediaContent->published_at->isPast() || $mediaContent->published_at->equalTo(now()));
        $this->assertSame(1, $admin->notifications()->count());
    }
}
