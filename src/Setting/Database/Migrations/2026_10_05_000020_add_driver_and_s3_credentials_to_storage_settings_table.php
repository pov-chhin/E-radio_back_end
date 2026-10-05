<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('storage_settings', function (Blueprint $table): void {
            // 'local' (the public disk) or 's3' (any S3-compatible object storage).
            $table->string('driver', 10)->default('local')->after('id');
            // Access key and secret are encrypted by the model (hence text); the rest is not secret.
            $table->text('s3_key')->nullable();
            $table->text('s3_secret')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_url')->nullable();
            $table->boolean('s3_use_path_style_endpoint')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('storage_settings', function (Blueprint $table): void {
            $table->dropColumn(['driver', 's3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_url', 's3_use_path_style_endpoint']);
        });
    }
};
