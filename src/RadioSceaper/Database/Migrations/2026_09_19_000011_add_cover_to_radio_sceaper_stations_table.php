<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->string('cover')->nullable()->after('stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->dropColumn('cover');
        });
    }
};
