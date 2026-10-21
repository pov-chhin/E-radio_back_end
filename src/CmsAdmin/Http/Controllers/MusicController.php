<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;

/** Songs, ambient tracks, the News / Health / Economy content and podcast episodes of Media Content, on a page of their own. */
class MusicController extends Controller
{
    /**
     * The tabs above the list, in this order: the categories each one lists, the one its entry in the "New"
     * menu starts, and that entry's icon. Audio (key `audio`; the old key `program` still
     * redirects to it) groups News, Health and Economy/Finance. Ambient Music is also a set of moods any content can carry, so its tab lists whatever
     * carries a mood (`tagged`) as well as anything filed under the category, and its "New" opens the form
     * with the category fixed on Ambient Music. "All" lists every category here.
     */
    public const TABS = [
        'song' => ['label' => 'Song', 'icon' => 'fa-music', 'categories' => ['song'], 'new' => 'song'],
        'audio' => ['label' => 'Audio', 'icon' => 'fa-headphones', 'categories' => ['news', 'health', 'economyfinance'], 'new' => 'news'],
        'podcast' => ['label' => 'Podcast Episode', 'icon' => 'fa-podcast', 'categories' => ['podcast'], 'new' => 'podcast'],
        'ambient' => ['label' => 'Ambient Music', 'icon' => 'fa-moon-o', 'categories' => ['ambient-music'], 'tagged' => 'ambient-music', 'new' => 'ambient-music'],
    ];

    /** "All": every tab's categories. */
    private const ALL_CATEGORIES = ['song', 'ambient-music', 'news', 'health', 'economyfinance', 'podcast'];

    public function index(Request $request): View|RedirectResponse
    {
        // The Audio tab used to be called "program": an old link or bookmark goes to the new address.
        if ($request->query('category') === 'program') {
            return redirect()->route('admin.music.index', ['category' => 'audio']);
        }

        // One tab per group; an unknown or empty `category` means "All".
        $tabs = collect(self::TABS)->map(fn (array $tab, string $key) => [...$tab, 'key' => $key, 'label' => __($tab['label'])]);
        $requested = $request->query('category');
        $activeTab = is_string($requested) ? $tabs->get($requested) : null;

        $mediaContents = MediaContent::with(['category', 'artists', 'author'])
            ->where(function ($query) use ($activeTab) {
                $query->whereIn('category_id', Category::whereIn('slug', $activeTab['categories'] ?? self::ALL_CATEGORIES)->select('id'));

                if ($activeTab['tagged'] ?? null) {
                    $query->orWhereHas('tags', fn ($tags) => $tags->whereIn('tags.category_id', Category::where('slug', $activeTab['tagged'])->select('id')));
                }
            })
            ->latest()
            ->get();

        // Category slug => the tab it belongs to: "All" names each row's group (Audio for News / Health /
        // Economy/Finance) instead of its category.
        $groupLabels = $tabs->flatMap(fn (array $tab) => collect($tab['categories'])->mapWithKeys(fn (string $slug) => [$slug => $tab['label']]));

        return view('cms-admin::music.index', compact('mediaContents', 'tabs', 'activeTab', 'groupLabels'));
    }
}
