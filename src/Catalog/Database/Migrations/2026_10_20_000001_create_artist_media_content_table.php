<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An item can credit several artists: they move from the single `media_contents.artist_id` to a link table. Every
 * item keeps the artist it has now.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('artist_media_content', function (Blueprint $table): void {
            $table->foreignId('media_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->primary(['media_content_id', 'artist_id']);
        });

        DB::table('media_contents')
            ->whereNotNull('artist_id')
            ->select('id', 'artist_id')
            ->chunkById(500, function ($rows): void {
                DB::table('artist_media_content')->insert(
                    $rows->map(fn ($row) => ['media_content_id' => $row->id, 'artist_id' => $row->artist_id])->all(),
                );
            });

        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('artist_id');
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->foreignId('artist_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
        });

        // One artist per item again: the one with the lowest id of those credited.
        DB::table('artist_media_content')
            ->select('media_content_id', DB::raw('min(artist_id) as artist_id'))
            ->groupBy('media_content_id')
            ->get()
            ->each(fn ($row) => DB::table('media_contents')->where('id', $row->media_content_id)->update(['artist_id' => $row->artist_id]));

        Schema::dropIfExists('artist_media_content');
    }
};
