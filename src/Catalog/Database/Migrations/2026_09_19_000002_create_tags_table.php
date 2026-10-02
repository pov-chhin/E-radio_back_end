<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('tags')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            // A name only needs to be unique among its siblings — e.g. "Genre"
            // exists under both Song > International and Song > Local.
            $table->unique(['parent_id', 'slug']);
        });

        $this->seedTree();
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }

    /**
     * The "By Tag" tree from the e-Radio app scope doc. Genre lists repeat
     * verbatim under Song > International and Song > Local.
     */
    private function seedTree(): void
    {
        $genres = ['Hiphop/Rap', 'Rock', 'EDM', 'Country', 'Pop', 'Folk'];

        $tree = [
            'Podcast' => ['Health', 'Economy/Finance', 'Education', 'Politic', 'Civilization'],
            'Health' => ['Mental Health', 'Physical Health'],
            'Economy/Finance' => ['International', 'Local'],
            'News' => ['Politic', 'Economy/Finance', 'Education', 'Sports', 'Civilization'],
            'Song' => [
                'International' => ['Genre' => $genres, 'Artist' => []],
                'Local' => ['Genre' => $genres, 'Artist' => []],
            ],
            'Ambient Music' => ['Reading', 'Studying', 'Yoga', 'Working', 'Lobby/Lounge', 'Khmer Traditional Music'],
        ];

        $insertNode = function (string $name, array $children, ?int $parentId) use (&$insertNode): void {
            $id = DB::table('tags')->insertGetId([
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => Str::slug($name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($children as $key => $value) {
                if (is_int($key)) {
                    $insertNode($value, [], $id);
                } else {
                    $insertNode($key, $value, $id);
                }
            }
        };

        foreach ($tree as $name => $children) {
            $insertNode($name, $children, null);
        }
    }
};
