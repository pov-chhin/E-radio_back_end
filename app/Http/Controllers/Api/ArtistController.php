<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use Illuminate\Http\Request;
use Src\Catalog\Models\Artist;
use Src\Catalog\Models\Tag;

class ArtistController extends Controller
{
    /**
     * Artists with something listeners can play, most-played first — the Music page's "Popular Artists".
     * `category` (slug) and `tag_ids` (comma-separated, descendants included) narrow which content counts.
     */
    public function index(Request $request)
    {
        $category = $request->query('category');
        $tagIds = array_filter(explode(',', (string) $request->query('tag_ids')), 'ctype_digit');
        $scopeIds = $tagIds ? Tag::idsWithDescendants($tagIds) : [];
        $limit = min(max((int) $request->query('limit', 10), 1), 50);

        $countable = fn ($query) => $query
            ->publiclyVisible()
            ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)))
            ->when($scopeIds, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $scopeIds)));

        $artists = Artist::query()
            ->where('status', 'active')
            ->whereHas('mediaContents', $countable)
            ->withSum(['mediaContents as total_plays' => $countable], 'play_count')
            ->orderByDesc('total_plays')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return ArtistResource::collection($artists);
    }
}
