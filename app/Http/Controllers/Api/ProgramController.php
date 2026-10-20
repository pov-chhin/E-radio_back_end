<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\Catalog\Models\Tag;

class ProgramController extends Controller
{
    /** Published shows that have at least one episode listeners can play, most recently updated first. */
    public function index(Request $request)
    {
        $query = Program::query()
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
            ->orderBy('name');

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        // Search: the show's name.
        if ($search = trim((string) $request->query('q'))) {
            // `!` escapes a literal % or _ the same way on MySQL and SQLite.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->whereRaw("programs.name like ? escape '!'", [$like]);
        }

        // A podcast type (Health, Economy/Finance, …) is chosen on the program itself.
        if ($tagId = $request->query('tag_id')) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $query->whereIn('tag_id', [$tag->id, ...$tag->descendantIds()]);
            }
        }

        return ProgramResource::collection($query->paginate(20));
    }

    /** One show with every episode listeners can play, newest first. */
    public function show(Program $program): JsonResource
    {
        abort_unless($program->is_published, 404);

        $program->load([
            'category',
            'type',
            'latestEpisode.artists',
            'episodes' => fn ($query) => $query->with(['category', 'artists'])->orderByDesc('published_at')->orderByDesc('id'),
        ])->loadCount('episodes');

        return new ProgramResource($program);
    }
}
