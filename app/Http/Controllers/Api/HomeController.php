<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MediaContentResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\StationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Predis\PredisException;
use RedisException;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;
use Src\RadioSceaper\Models\RadioSceaperStation;

/** The complete, small first screen in one network request. */
class HomeController extends Controller
{
    private const CACHE_KEY = 'api:home:v1';

    private const CACHE_SECONDS = 60;

    private const STATION_LIMIT = 10;

    private const MEDIA_LIMIT = 9;

    private const FEED_LIMIT = 6;

    private const PROGRAM_LIMIT = 10;

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $home = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_SECONDS,
                fn () => $this->payload($request),
            );
        } catch (PredisException|RedisException) {
            // Public browsing must keep working during a cache outage.
            $home = $this->payload($request);
        }

        return response()
            ->json(['data' => $home])
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
    }

    private function payload(Request $request): array
    {
        $stations = RadioSceaperStation::query()
            ->where('is_published', true)
            ->inListenerOrder()
            ->limit(self::STATION_LIMIT)
            ->get();

        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        $programs = Program::query()
            ->where('is_published', true)
            ->whereHas('episodes')
            ->with(['category', 'type', 'latestEpisode.artists'])
            ->withCount('episodes')
            ->orderByDesc(
                MediaContent::query()->select('published_at')
                    ->whereColumn('program_id', 'programs.id')
                    ->publiclyVisible()
                    ->orderByDesc('published_at')
                    ->limit(1)
            )
            ->orderBy('name')
            ->limit(self::PROGRAM_LIMIT)
            ->get();

        $artists = Artist::query()
            ->where('status', 'active')
            ->whereHas('mediaContents', fn (Builder $query) => $query
                ->publiclyVisible()
                ->whereHas('category', fn (Builder $category) => $category->where('slug', 'song')))
            ->withSum(['mediaContents as total_plays' => fn (Builder $query) => $query
                ->publiclyVisible()
                ->whereHas('category', fn (Builder $category) => $category->where('slug', 'song'))], 'play_count')
            ->orderByDesc('total_plays')
            ->orderBy('name')
            ->limit(10)
            ->get();

        $byParent = Tag::query()
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'image', 'image_disk'])
            ->groupBy('parent_id');

        return [
            'stations' => StationResource::collection($stations)->resolve($request),
            'trending' => MediaContentResource::collection(
                $this->media()->orderByDesc('play_count')->limit(self::MEDIA_LIMIT)->get(),
            )->resolve($request),
            'new_releases' => MediaContentResource::collection(
                $this->media()->orderByDesc('published_at')->limit(self::MEDIA_LIMIT)->get(),
            )->resolve($request),
            'feeds' => collect(['news', 'economyfinance', 'health', 'song'])
                ->mapWithKeys(fn (string $slug) => [$slug => MediaContentResource::collection(
                    $this->media()
                        ->whereHas('category', fn (Builder $query) => $query->where('slug', $slug))
                        ->orderByDesc('published_at')
                        ->limit($slug === 'song' ? 18 : self::FEED_LIMIT)
                        ->get(),
                )->resolve($request)])
                ->all(),
            'programs' => ProgramResource::collection($programs)->resolve($request),
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'tags' => $this->tagTree($byParent, null),
            'artists' => ArtistResource::collection($artists)->resolve($request),
        ];
    }

    private function media(): Builder
    {
        return MediaContent::query()
            ->with(['category', 'artists'])
            ->publiclyVisible();
    }

    private function tagTree(Collection $byParent, ?int $parentId): array
    {
        return $byParent->get($parentId, collect())->map(fn (Tag $tag) => [
            'id' => (string) $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'image_url' => $tag->image_url,
            'children' => $this->tagTree($byParent, $tag->id),
        ])->values()->all();
    }
}
