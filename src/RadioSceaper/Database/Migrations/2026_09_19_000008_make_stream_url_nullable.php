<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Manually-added channels (see "Add Channel") may not have a stream URL
     * yet. No doctrine/dbal in this app, so column modification is driver-specific:
     * MySQL supports MODIFY in place; SQLite needs the table rebuilt.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildForSqlite();

            return;
        }

        DB::statement('ALTER TABLE radio_sceaper_stations MODIFY stream_url VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE radio_sceaper_stations MODIFY stream_url VARCHAR(255) NOT NULL');
    }

    private function rebuildForSqlite(): void
    {
        Schema::create('radio_sceaper_stations_tmp', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('stream_url')->nullable();
            $table->unsignedSmallInteger('bitrate')->nullable();
            $table->unsignedInteger('votes')->default(0);
            $table->string('tags')->nullable();
            $table->string('radio_browser_uuid')->nullable()->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO radio_sceaper_stations_tmp
                (id, name, stream_url, bitrate, votes, tags, radio_browser_uuid, is_published, created_at, updated_at)
            SELECT id, name, stream_url, bitrate, votes, tags, radio_browser_uuid, is_published, created_at, updated_at
            FROM radio_sceaper_stations
        ');

        Schema::drop('radio_sceaper_stations');
        Schema::rename('radio_sceaper_stations_tmp', 'radio_sceaper_stations');
    }
};
