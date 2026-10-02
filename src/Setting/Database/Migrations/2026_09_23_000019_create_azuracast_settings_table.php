<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('azuracast_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('station_id')->default('azuratest');
            $table->unsignedInteger('timeout')->default(5);
            $table->timestamps();
        });

        // Singleton row, seeded from whatever the .env-driven config
        // currently resolves to so existing behavior doesn't change until
        // an admin edits it from the Station settings > Integration tab.
        DB::table('azuracast_settings')->insert([
            'url' => config('services.azuracast.url'),
            'api_key' => config('services.azuracast.api_key'),
            'station_id' => (string) config('services.azuracast.station_id', 'azuratest'),
            'timeout' => (int) config('services.azuracast.timeout', 5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('azuracast_settings');
    }
};
