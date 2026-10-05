<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ISO 3166-1 alpha-2 of where the account was last seen (see RecordUserCountry); feeds the dashboard's country map.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('country', 2)->nullable()->index()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('country');
        });
    }
};
