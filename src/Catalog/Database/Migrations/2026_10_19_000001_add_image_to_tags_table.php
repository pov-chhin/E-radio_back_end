<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A picture for the tag's card in the app (the Ambient Music moods have one each). `image_disk` is which
        // disk it lives on, NULL meaning the local public disk — same as the artist and program images.
        Schema::table('tags', function (Blueprint $table): void {
            $table->string('image')->nullable()->after('slug');
            $table->string('image_disk', 30)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn(['image', 'image_disk']);
        });
    }
};
