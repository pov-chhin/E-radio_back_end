<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A program's "podcast type" is one of the tags directly under the top-level Podcast tag (Health,
     * Economy/Finance, Education, Politic, Civilization), kept on the program itself.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->foreignId('tag_id')->nullable()->after('category_id')->constrained('tags')->nullOnDelete();
        });

        // Programs already filed under a category that a Podcast type is named after (Health,
        // Economy/Finance) take that type; the rest have none until one is picked.
        $podcastRoot = DB::table('tags')->whereNull('parent_id')->where('slug', 'podcast')->value('id');
        if (! $podcastRoot) {
            return;
        }

        $types = DB::table('tags')->where('parent_id', $podcastRoot)->pluck('id', 'slug');
        $programs = DB::table('programs')
            ->join('categories', 'categories.id', '=', 'programs.category_id')
            ->get(['programs.id', 'categories.slug as category_slug']);

        foreach ($programs as $program) {
            if ($types->has($program->category_slug)) {
                DB::table('programs')->where('id', $program->id)->update(['tag_id' => $types[$program->category_slug]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tag_id');
        });
    }
};
