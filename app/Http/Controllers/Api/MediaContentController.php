<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaContentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Tag;

class MediaContentController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaContent::query()
            ->with(['category', 'artists'])
            ->where('is_published', true)
            ->where('processing_status', 'ready')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));

        if ($request->query('sort') === 'trending') {
            $query->orderByDesc('play_count');
        } else {
            $query->orderByDesc('published_at');
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($tagId = $request->query('tag_id')) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $tagIds = [$tag->id, ...$tag->descendantIds()];
                $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
            }
        }

        // Several tags at once (comma-separated), matching any of them or their descendants.
        if ($tagIds = array_filter(explode(',', (string) $request->query('tag_ids')), 'ctype_digit')) {
            $allIds = Tag::idsWithDescendants($tagIds);
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $allIds));
        }

        if ($artistId = $request->query('artist_id')) {
            $query->whereHas('artists', fn ($artist) => $artist->where('artists.id', $artistId));
        }

        // Search: the title, or the name of any artist credited.
        if ($search = trim((string) $request->query('q'))) {
            // `!` escapes a literal % or _ the same way on MySQL and SQLite.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q
                ->whereRaw("media_contents.title like ? escape '!'", [$like])
                ->orWhereHas('artists', fn ($artist) => $artist->whereRaw("artists.name like ? escape '!'", [$like])));
        }

        // Batch lookup by id, e.g. resolving a locally-stored "recently played" or favorites list.
        // It wants exactly those items, not a page of them, so it skips pagination (capped at 100).
        if ($ids = $request->query('ids')) {
            return MediaContentResource::collection(
                $query->whereIn('id', array_slice(explode(',', $ids), 0, 100))->get()
            );
        }

        // A bounded `limit` (e.g. a homepage preview row) skips pagination
        // entirely rather than mixing the two.
        if ($limit = $request->query('limit')) {
            return MediaContentResource::collection($query->limit((int) $limit)->get());
        }

        return MediaContentResource::collection($query->paginate(20));
    }

    /** Counts one play (a "view") of a published item and returns the new total. */
    public function recordPlay(MediaContent $mediaContent): JsonResponse
    {
        abort_unless($mediaContent->is_published, 404);

        $mediaContent->increment('play_count');

        return response()->json(['play_count' => $mediaContent->play_count]);
    }

    /** Resolve the latest playable URL immediately before a client starts playback. */
    public function show(MediaContent $mediaContent): JsonResource
    {
        abort_unless(
            $mediaContent->is_published
            && $mediaContent->processing_status === 'ready'
            && (! $mediaContent->published_at || $mediaContent->published_at->isPast()),
            404,
        );

        return new MediaContentResource($mediaContent->load(['category', 'artists']));
    }
}
