<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MinIO is no longer a storage type: uploads fall back to the local disk instead of a connection that is gone.
        DB::table('storage_settings')->where('driver', 'minio')->update(['driver' => 'local']);

        Schema::dropIfExists('minio_settings');
    }

    public function down(): void
    {
        Schema::create('minio_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('s3_key')->nullable();
            $table->text('s3_secret')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_url')->nullable();
            $table->boolean('s3_use_path_style_endpoint')->default(true);
            $table->timestamps();
        });
    }
};
