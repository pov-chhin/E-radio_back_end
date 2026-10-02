<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_sceaper_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('stream_url');
            $table->unsignedSmallInteger('bitrate')->nullable();
            $table->unsignedInteger('votes')->default(0);
            $table->string('tags')->nullable();
            $table->string('radio_browser_uuid')->nullable()->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_sceaper_stations');
    }
};
