<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Where the app lists the channel: 1, 2, 3… from the top; 0 (not set — every row that existed before this column) goes below them, by id.
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
