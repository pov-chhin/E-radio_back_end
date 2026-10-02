<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->dropColumn(['location', 'genre', 'language']);
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('bio');
            $table->string('genre')->nullable()->after('location');
            $table->string('language')->nullable()->after('genre');
        });
    }
};
