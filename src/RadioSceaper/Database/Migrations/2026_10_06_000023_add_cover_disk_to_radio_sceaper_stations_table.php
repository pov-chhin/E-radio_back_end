<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which disk the cover lives on, so switching storage in the settings only affects new uploads.
        // NULL means the local public disk — every row that existed before this column.
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->string('cover_disk', 30)->nullable()->after('cover');
        });
    }

    public function down(): void
    {
        Schema::table('radio_sceaper_stations', function (Blueprint $table): void {
            $table->dropColumn('cover_disk');
        });
    }
};
