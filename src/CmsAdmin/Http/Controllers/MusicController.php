<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;

/** Songs, ambient tracks and the News / Health / Economy content of Media Content, by category, on a page of their own. */
class MusicController extends Controller
{
    /** The categories that count as music (the ones Programs leave out). */
    public const CATEGORY_SLUGS = ['song', 'ambient-music'];

    /**
     * The tabs above the list, in this order. Ambient tracks are songs with moods, so Ambient Music has
     * no tab of its own (anything still filed under it shows in "All"); podcasts are managed under Programs.
     */
    public const TAB_SLUGS = ['song', 'news', 'health', 'economyfinance'];

    public function index(Request $request): View
    {
        // One tab per category; an unknown or empty `category` means "All".
        $categories = Category::whereIn('slug', self::TAB_SLUGS)->get()
            ->sortBy(fn (Category $category) => array_search($category->slug, self::TAB_SLUGS, true))
            ->values();
        $activeCategory = $categories->firstWhere('slug', $request->query('category'));

        $mediaContents = MediaContent::with(['category', 'artist', 'author'])
            ->whereIn('category_id', $activeCategory ? [$activeCategory->id] : $this->allCategoryIds($categories))
            ->latest()
            ->get();

        return view('cms-admin::music.index', compact('mediaContents', 'categories', 'activeCategory'));
    }

    /** "All": the tabs' categories, plus Ambient Music for anything still filed under it. */
    private function allCategoryIds(Collection $tabCategories): array
    {
        return [
            ...$tabCategories->modelKeys(),
            ...Category::whereIn('slug', self::CATEGORY_SLUGS)->pluck('id')->all(),
        ];
    }
}
