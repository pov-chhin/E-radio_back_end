<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_studio_settings', function (Blueprint $table): void {
            $table->id();
            // Where the sidebar's "Radio Studio" link goes. Empty hides the link.
            $table->string('url', 2048)->nullable();
            $table->timestamps();
        });

        // Singleton row, so the link works as soon as this has run and an admin edits it under Settings → Radio Studio.
        DB::table('radio_studio_settings')->insert([
            'url' => 'http://studio.devams.site/dashboard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_studio_settings');
    }
};
