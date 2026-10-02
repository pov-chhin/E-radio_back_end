<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropColumn('artist_or_author');
            $table->foreignId('artist_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
            // References the shared `users` table — the app-side (type=customer) account credited as the author.
            $table->foreignId('author_id')->nullable()->after('artist_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('artist_id');
            $table->dropConstrainedForeignId('author_id');
            $table->string('artist_or_author')->nullable();
        });
    }
};
