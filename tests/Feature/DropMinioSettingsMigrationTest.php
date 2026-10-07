<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Setting\Models\StorageSetting;
use Tests\TestCase;

/** A database that already ran the MinIO migration — as the live site did — must lose the table and fall back to local uploads. */
class DropMinioSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('src/Setting/Database/Migrations/2026_10_07_000023_drop_minio_settings_table.php');
    }

    public function test_it_drops_the_minio_table_and_switches_a_minio_storage_back_to_local(): void
    {
        Schema::create('minio_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('s3_bucket')->nullable();
        });
        StorageSetting::firstOrFail()->update(['driver' => 'minio']);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('minio_settings'));
        $this->assertSame('local', StorageSetting::firstOrFail()->driver);
    }

    public function test_it_leaves_other_storage_types_alone_and_runs_on_a_database_without_the_table(): void
    {
        StorageSetting::firstOrFail()->update(['driver' => 's3']);

        $this->migration()->up();

        $this->assertSame('s3', StorageSetting::firstOrFail()->driver);
        $this->assertSame(0, DB::table('storage_settings')->where('driver', 'minio')->count());
    }

    public function test_a_stale_minio_storage_type_is_no_longer_offered_and_uploads_stay_local(): void
    {
        $this->assertNotContains('minio', StorageSetting::DRIVERS);

        $setting = StorageSetting::firstOrFail();
        $setting->forceFill(['driver' => 'minio'])->save();
        $setting->refresh()->applyToConfig();

        $this->assertNull($setting->remoteDisk());
        $this->assertSame('public', config('services.media_storage.disk'));
    }
}
