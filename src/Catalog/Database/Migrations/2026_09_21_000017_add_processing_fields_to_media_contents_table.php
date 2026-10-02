<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->string('processing_status', 20)->default('ready')->after('stream');
            $table->string('source_path')->nullable()->after('processing_status');
            $table->text('processing_error')->nullable()->after('source_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_contents', function (Blueprint $table): void {
            $table->dropColumn(['processing_status', 'source_path', 'processing_error']);
        });
    }
};
