<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('minio_settings', function (Blueprint $table): void {
            $table->id();
            // Same shape as the S3 columns on storage_settings: MinIO speaks the S3 protocol.
            $table->text('s3_key')->nullable();
            $table->text('s3_secret')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_url')->nullable();
            $table->boolean('s3_use_path_style_endpoint')->default(true);
            $table->timestamps();
        });

        // Singleton row, seeded with the usual bucket and region so the admin only has to add the address and keys.
        DB::table('minio_settings')->insert([
            's3_bucket' => config('services.minio.bucket'),
            's3_region' => config('services.minio.region'),
            's3_endpoint' => config('services.minio.endpoint'),
            's3_use_path_style_endpoint' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('minio_settings');
    }
};
