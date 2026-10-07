<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_stations', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->string('frequency')->nullable();
            $table->text('description')->nullable(); $table->string('status')->default('active'); $table->unsignedBigInteger('azuracast_id')->nullable(); $table->timestamps();
        });
        Schema::create('radio_streams', function (Blueprint $table): void {
            $table->id(); $table->foreignId('radio_station_id')->constrained()->cascadeOnDelete(); $table->string('protocol', 24);
            $table->string('codec', 16); $table->unsignedSmallInteger('bitrate')->nullable(); $table->string('url'); $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_primary')->default(false); $table->string('health_status', 24)->default('unknown'); $table->timestamps();
        });
        Schema::create('radio_playlists', function (Blueprint $table): void {
            $table->id(); $table->foreignId('radio_station_id')->constrained()->cascadeOnDelete(); $table->string('name');
            $table->string('type', 24)->default('general'); $table->unsignedSmallInteger('weight')->default(1); $table->boolean('is_enabled')->default(true); $table->timestamps();
        });
        Schema::create('radio_schedules', function (Blueprint $table): void {
            $table->id(); $table->foreignId('radio_station_id')->constrained()->cascadeOnDelete(); $table->string('title'); $table->string('host')->nullable();
            $table->dateTime('starts_at'); $table->dateTime('ends_at'); $table->json('days')->nullable(); $table->string('source_type', 24)->default('playlist'); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_schedules'); Schema::dropIfExists('radio_playlists'); Schema::dropIfExists('radio_streams'); Schema::dropIfExists('radio_stations');
    }
};
