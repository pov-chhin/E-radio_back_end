<?php

namespace Src\Catalog\Console\Commands;

use App\Support\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Src\Catalog\Models\Tag;
use Src\Catalog\Services\ImageVariantProcessor;

class ImportAmbientCardImagesCommand extends Command
{
    protected $signature = 'tags:import-ambient-cards {--force : Replace a picture a mood already has}';

    protected $description = 'Store the original Ambient Music card pictures (database/data/ambient-cards) as the pictures of their moods, so the app loads them from the database';

    /** Mood tag slug => the file in database/data/ambient-cards. */
    private const CARDS = [
        'working' => 'working.png',
        'lobbylounge' => 'relaxing.png',
        'studying' => 'studying.png',
        'yoga' => 'yoga.png',
        'Sports' => 'sports.png',
    ];

    /** Must match the size the admin crops a tag picture to (CmsAdmin TagController). */
    private const WIDTH = 616;

    private const HEIGHT = 296;

    public function handle(ImageVariantProcessor $imageProcessor): int
    {
        $ambient = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->first();

        if ($ambient === null) {
            $this->error('There is no top-level "ambient-music" tag.');

            return self::FAILURE;
        }

        foreach (self::CARDS as $slug => $file) {
            $tag = Tag::where('parent_id', $ambient->id)->where('slug', $slug)->first();

            if ($tag === null) {
                $this->warn("  {$slug}: no such mood under Ambient Music — skipped");

                continue;
            }

            if (filled($tag->image) && ! $this->option('force')) {
                $this->line("  {$slug}: already has a picture — skipped (use --force to replace it)");

                continue;
            }

            $source = database_path("data/ambient-cards/{$file}");
            $path = $imageProcessor->process(
                new UploadedFile($source, $file, 'image/png', null, true),
                config('services.media_storage.image_directory'),
                self::WIDTH, self::HEIGHT,
                disk: MediaStorage::current(),
            );

            if (filled($tag->image) && ! str_starts_with($tag->image, 'http')) {
                MediaStorage::disk($tag->image_disk)->delete($tag->image);
            }

            $tag->update(['image' => $path, 'image_disk' => MediaStorage::column()]);
            $this->info("  {$slug}: stored {$path}");
        }

        return self::SUCCESS;
    }
}
