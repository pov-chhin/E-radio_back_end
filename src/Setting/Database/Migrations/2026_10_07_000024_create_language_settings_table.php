<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('language_settings', function (Blueprint $table): void {
            $table->id();
            // The admin panel's default language ('en' or 'km'); an admin's own choice (users.locale) wins over it.
            $table->string('locale', 5)->default('en');
            $table->timestamps();
        });

        // Singleton row, so the panel stays in English until an admin changes it under Settings → Language.
        DB::table('language_settings')->insert([
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('language_settings');
    }
};
