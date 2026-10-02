<?php

namespace Src\Social\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Src\Catalog\Models\MediaContent;
use Src\RadioSceaper\Models\RadioSceaperStation;

class SocialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Short aliases matching the app's own id scheme (NowPlayingItem uses
        // 'station:<id>' / 'media:<id>') instead of leaking full class names.
        // Not enforceMorphMap: Sanctum's PersonalAccessToken has its own
        // tokenable morph relation on this same User model, unrelated to
        // comments/bookmarks, and enforcing would break it app-wide.
        Relation::morphMap([
            'station' => RadioSceaperStation::class,
            'media' => MediaContent::class,
        ]);
    }
}
