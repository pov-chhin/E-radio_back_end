<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Src\Catalog\Models\Tag;

class TagController extends Controller
{
    /** Full tag tree in one call (only 42 rows total) so the client can filter/drill down client-side. */
    public function index()
    {
        $byParent = Tag::orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'image', 'image_disk', 'banner_image', 'banner_image_disk'])->groupBy('parent_id');

        return response()->json(['data' => $this->buildTree($byParent, null)]);
    }

    private function buildTree(Collection $byParent, ?int $parentId): array
    {
        return $byParent->get($parentId, collect())->map(fn (Tag $tag) => [
            'id' => (string) $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'image_url' => $tag->image_url,
            'banner_image_url' => $tag->banner_image_url,
            'children' => $this->buildTree($byParent, $tag->id),
        ])->values()->all();
    }
}
