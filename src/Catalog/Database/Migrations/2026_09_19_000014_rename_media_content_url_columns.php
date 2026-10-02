<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Free up the `*_url` names for computed accessors (matching Artist's
     * photo/photoUrl and RadioSceaperStation's cover/coverUrl) now that
     * both fields accept an upload as well as a pasted URL.
     */
    public function up(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->renameColumn('stream_url', 'stream');
            $table->renameColumn('thumbnail_url', 'thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->renameColumn('stream', 'stream_url');
            $table->renameColumn('thumbnail', 'thumbnail_url');
        });
    }
};
