<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The YouTube link an episode's audio is fetched from. `stream` ends up as our own converted file; this
        // keeps where it came from (and lets the queue job notice the link was changed while it was working).
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->string('source_url', 2048)->nullable()->after('source_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropColumn('source_url');
        });
    }
};
