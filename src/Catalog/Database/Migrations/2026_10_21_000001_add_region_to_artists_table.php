<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Where the artist is from: `international` or `local` (the same two regions the media form's tag list is
        // narrowed by). NULL until one is picked, so the artists that exist already are left as they are. The
        // Status column stays, though the form no longer offers it (new artists keep its default, `active`).
        Schema::table('artists', function (Blueprint $table): void {
            $table->string('region', 20)->nullable()->after('stage_name');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->dropColumn('region');
        });
    }
};
