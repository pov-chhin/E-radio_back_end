<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which disk each stored image lives on, so switching storage in the settings only affects new uploads.
        // NULL means the local public disk — every row that existed before these columns.
        Schema::table('programs', function (Blueprint $table): void {
            $table->string('cover_image_disk', 30)->nullable()->after('cover_image');
        });

        Schema::table('artists', function (Blueprint $table): void {
            $table->string('photo_disk', 30)->nullable()->after('photo');
            $table->string('cover_disk', 30)->nullable()->after('cover');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('cover_image_disk');
        });

        Schema::table('artists', function (Blueprint $table): void {
            $table->dropColumn(['photo_disk', 'cover_disk']);
        });
    }
};
