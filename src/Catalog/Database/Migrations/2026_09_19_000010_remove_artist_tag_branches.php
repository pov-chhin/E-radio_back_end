<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Artists are now a first-class `artists` table, not tag-tree nodes —
     * drop the empty "Artist" branches the tags migration originally seeded
     * under Song > International and Song > Local.
     */
    public function up(): void
    {
        // `parent_id` cascades on delete, so any children added under a
        // branch since (unlikely, but possible on an already-migrated
        // database) are removed along with it.
        DB::table('tags')->where('name', 'Artist')->delete();
    }

    public function down(): void
    {
        // Not reversible — the original seed migration already ran and won't re-fire.
    }
};
