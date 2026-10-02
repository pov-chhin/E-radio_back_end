<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('storage_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('image_directory')->default('media-contents/thumbnails');
            $table->string('audio_directory')->default('media-contents/streams');
            $table->timestamps();
        });

        // Singleton row, seeded from whatever these currently resolve to so
        // existing uploads keep landing in the same place until an admin
        // edits it from /admin/settings.
        DB::table('storage_settings')->insert([
            'image_directory' => config('services.media_storage.image_directory', 'media-contents/thumbnails'),
            'audio_directory' => config('services.media_storage.audio_directory', 'media-contents/streams'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_settings');
    }
};
