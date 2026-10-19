<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The illustration on the tag's page in the app (an Ambient Music mood's banner — the character beside its
        // name), kept with its transparency. `banner_image_disk` is which disk it lives on, NULL meaning the local
        // public disk — same as `image`.
        Schema::table('tags', function (Blueprint $table): void {
            $table->string('banner_image')->nullable()->after('image_disk');
            $table->string('banner_image_disk', 30)->nullable()->after('banner_image');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn(['banner_image', 'banner_image_disk']);
        });
    }
};
