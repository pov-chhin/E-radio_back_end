<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every tag sits under a category: the top-level tag of each tree (Podcast, Health, Song, …) is the
     * category's, and everything nested under it follows.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('parent_id')->constrained()->nullOnDelete();
        });

        // The existing trees belong to the category of the same name: match each top-level tag by slug,
        // then hand its category down to every tag beneath it.
        $categories = DB::table('categories')->pluck('id', 'slug');
        $childrenOf = DB::table('tags')->get(['id', 'parent_id', 'slug'])->groupBy('parent_id');

        foreach ($childrenOf->get(null, collect()) as $root) {
            $categoryId = $categories[$root->slug] ?? null;
            if ($categoryId === null) {
                continue;
            }

            $ids = [$root->id];
            $pending = [$root->id];
            while ($pending) {
                foreach ($childrenOf->get(array_pop($pending), collect()) as $child) {
                    $ids[] = $child->id;
                    $pending[] = $child->id;
                }
            }

            DB::table('tags')->whereIn('id', $ids)->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
