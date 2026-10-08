<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_settings', function (Blueprint $table): void {
            // Encrypted by StorageSetting's cast. The plain YouTube session is never stored in the filesystem.
            $table->longText('youtube_cookies')->nullable()->after('audio_directory');
        });
    }

    public function down(): void
    {
        Schema::table('storage_settings', function (Blueprint $table): void {
            $table->dropColumn('youtube_cookies');
        });
    }
};
