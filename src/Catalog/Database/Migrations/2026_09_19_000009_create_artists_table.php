<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stage_name')->nullable();
            $table->string('photo')->nullable();
            $table->string('cover')->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('genre')->nullable();
            $table->string('language')->nullable();
            $table->boolean('verified')->default(false);
            $table->json('social_links')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
