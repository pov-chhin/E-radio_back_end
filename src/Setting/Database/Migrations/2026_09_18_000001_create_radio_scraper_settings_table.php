<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_scraper_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('country', 2)->default('KH');
            $table->timestamps();
        });

        // Singleton row, seeded from whatever the .env-driven config
        // currently resolves to so existing behavior doesn't change until
        // an admin edits it from /admin/settings.
        DB::table('radio_scraper_settings')->insert([
            'enabled' => (bool) config('services.radio_browser.enabled', false),
            'country' => strtoupper((string) config('services.radio_browser.country', 'KH')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_scraper_settings');
    }
};
