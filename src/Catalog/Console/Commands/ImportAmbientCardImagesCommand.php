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

    protected $description = 'Store the original Ambient Music pictures — each mood\'s card (database/data/ambient-cards) and banner illustration (database/data/ambient-banners) — on their moods, so the app loads them from the database';

    /**
     * The tag column each kind of picture goes in, with the folder its files are in (under database/data), the file
     * for each mood slug, and the size it is stored at — the same the admin uses (CmsAdmin TagController).
     */
    private const PICTURES = [
        'image' => [
            'directory' => 'ambient-cards',
            'width' => 616,
            'height' => 296,
            'crop' => true,
            'files' => [
                'working' => 'working.png',
                'lobbylounge' => 'relaxing.png',
                'studying' => 'studying.png',
                'yoga' => 'yoga.png',
                'Sports' => 'sports.png',
            ],
        ],
        'banner_image' => [
            'directory' => 'ambient-banners',
            'width' => 480,
            'height' => 480,
            'crop' => false,
            'files' => [
                'working' => 'working.png',
                'lobbylounge' => 'relaxing.png',
                'studying' => 'studying.png',
                'yoga' => 'yoga.png',
                'reading' => 'reading.png',
                'Sports' => 'sports.png',
                'khmer-traditional-music' => 'khmer.png',
            ],
        ],
    ];

    public function handle(ImageVariantProcessor $imageProcessor): int
    {
        $ambient = Tag::where('slug', 'ambient-music')->whereNull('parent_id')->first();

        if ($ambient === null) {
            $this->error('There is no top-level "ambient-music" tag.');

            return self::FAILURE;
        }

        foreach (self::PICTURES as $column => $picture) {
            $this->info($column === 'image' ? 'Cards:' : 'Banners:');

            foreach ($picture['files'] as $slug => $file) {
                $tag = Tag::where('parent_id', $ambient->id)->where('slug', $slug)->first();

                if ($tag === null) {
                    $this->warn("  {$slug}: no such mood under Ambient Music — skipped");

                    continue;
                }

                if (filled($tag->{$column}) && ! $this->option('force')) {
                    $this->line("  {$slug}: already has one — skipped (use --force to replace it)");

                    continue;
                }

                $path = $imageProcessor->process(
                    new UploadedFile(database_path("data/{$picture['directory']}/{$file}"), $file, 'image/png', null, true),
                    config('services.media_storage.image_directory'),
                    $picture['width'], $picture['height'],
                    crop: $picture['crop'],
                    disk: MediaStorage::current(),
                );

                if (filled($tag->{$column}) && ! str_starts_with($tag->{$column}, 'http')) {
                    MediaStorage::disk($tag->{"{$column}_disk"})->delete($tag->{$column});
                }

                $tag->update([$column => $path, "{$column}_disk" => MediaStorage::column()]);
                $this->line("  {$slug}: stored {$path}");
            }
        }

        return self::SUCCESS;
    }
}
