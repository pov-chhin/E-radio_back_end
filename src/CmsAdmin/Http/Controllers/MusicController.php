<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;

/** Songs, ambient tracks, the News / Health / Economy content and podcast episodes of Media Content, on a page of their own. */
class MusicController extends Controller
{
    /**
     * The tabs above the list, in this order: the categories each one lists, the one its "New" button
     * starts, and that button's name in the singular ("New Podcast"). Program groups News, Health and
     * Economy/Finance. Ambient Music is also a set of moods any content can carry, so its tab lists whatever
     * carries a mood (`tagged`) as well as anything filed under the category, and its "New" opens the form
     * with the category fixed on Ambient Music. "All" lists every category here.
     */
    public const TABS = [
        'song' => ['label' => 'Song', 'new_name' => 'Song', 'categories' => ['song'], 'new' => 'song'],
        'program' => ['label' => 'Program', 'new_name' => 'Program', 'categories' => ['news', 'health', 'economyfinance'], 'new' => 'news'],
        'podcast' => ['label' => 'Podcasts', 'new_name' => 'Podcast', 'categories' => ['podcast'], 'new' => 'podcast'],
        'ambient' => ['label' => 'Ambient Music', 'new_name' => 'Ambient Music', 'categories' => ['ambient-music'], 'tagged' => 'ambient-music', 'new' => 'ambient-music'],
    ];

    /** "All": every tab's categories. */
    private const ALL_CATEGORIES = ['song', 'ambient-music', 'news', 'health', 'economyfinance', 'podcast'];

    public function index(Request $request): View
    {
        // One tab per group; an unknown or empty `category` means "All".
        $tabs = collect(self::TABS)->map(fn (array $tab, string $key) => [...$tab, 'key' => $key, 'label' => __($tab['label']), 'new_name' => __($tab['new_name'])]);
        $requested = $request->query('category');
        $activeTab = is_string($requested) ? $tabs->get($requested) : null;

        $mediaContents = MediaContent::with(['category', 'artist', 'author'])
            ->where(function ($query) use ($activeTab) {
                $query->whereIn('category_id', Category::whereIn('slug', $activeTab['categories'] ?? self::ALL_CATEGORIES)->select('id'));

                if ($activeTab['tagged'] ?? null) {
                    $query->orWhereHas('tags', fn ($tags) => $tags->whereIn('tags.category_id', Category::where('slug', $activeTab['tagged'])->select('id')));
                }
            })
            ->latest()
            ->get();

        // "New" follows the tab; All starts a Song.
        $newTab = $activeTab ?? $tabs['song'];

        // Category slug => the tab it belongs to: "All" names each row's group (Program for News / Health /
        // Economy/Finance) instead of its category.
        $groupLabels = $tabs->flatMap(fn (array $tab) => collect($tab['categories'])->mapWithKeys(fn (string $slug) => [$slug => $tab['label']]));

        return view('cms-admin::music.index', compact('mediaContents', 'tabs', 'activeTab', 'newTab', 'groupLabels'));
    }
}
