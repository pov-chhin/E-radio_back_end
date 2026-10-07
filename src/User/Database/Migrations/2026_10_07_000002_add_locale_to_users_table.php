<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The language this admin chose for the panel ('en' or 'km'); NULL follows the site default in Settings → Language.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
