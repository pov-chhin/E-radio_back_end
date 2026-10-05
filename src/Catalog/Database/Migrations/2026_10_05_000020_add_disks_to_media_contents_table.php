<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which disk each stored file lives on, so switching storage in the settings only affects new uploads.
        // NULL means the local public disk — every row that existed before this column.
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->string('stream_disk', 30)->nullable()->after('stream');
            $table->string('thumbnail_disk', 30)->nullable()->after('thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropColumn(['stream_disk', 'thumbnail_disk']);
        });
    }
};
