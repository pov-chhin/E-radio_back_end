<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AdminCache;
use App\Support\OnlineUsers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Src\Catalog\Models\Category;
use Src\Catalog\Models\MediaContent;
use Src\Catalog\Models\Program;
use Src\RadioSceaper\Models\RadioSceaperStation;
use Src\Setting\Models\RadioScraperSetting;

class DashboardController extends Controller
{
    /** The counts and lists change slowly, so a minute-old dashboard is fine and saves about nine queries a load. */
    private const OVERVIEW_CACHE_SECONDS = 60;

    public function index(): View
    {
        $overview = AdminCache::remember('dashboard:overview', self::OVERVIEW_CACHE_SECONDS, fn () => $this->overview());
        $online = OnlineUsers::snapshot();

        return view('cms-admin::dashboard', [
            'stats' => [...$overview['stats'], 'users_online' => $online['count']],
            // Not cached: an admin flips this on the settings page and expects the dashboard to follow at once.
            'scraperSetting' => RadioScraperSetting::first(),
            'recentChannels' => $overview['recentChannels'],
            'recentPodcasts' => $overview['recentPodcasts'],
            // `??`: a dashboard cached a minute ago, before this list existed, has no such key.
            'recentAudio' => $overview['recentAudio'] ?? collect(),
            'topMusic' => $overview['topMusic'],
            'onlineUsers' => $online['users'],
            'countries' => $overview['countries'],
            'locatedUsers' => $overview['countries']->sum(),
        ]);
    }

    /** What the dashboard shows apart from who is online and the scraper setting — the cache-miss read from MySQL. */
    private function overview(): array
    {
        return [
            'stats' => [
                'channels_total' => RadioSceaperStation::count(),
                'channels_published' => RadioSceaperStation::where('is_published', true)->count(),
                'users_total' => DB::table('users')->count(),
            ],
            'recentChannels' => RadioSceaperStation::latest()->take(5)->get(),
            'recentPodcasts' => Program::with('type')
                ->withCount('mediaContents')
                ->withSum('mediaContents', 'play_count')
                ->latest()
                ->take(8)
                ->get(),
            // The Audio group of the Content page: News, Health and Economy/Finance.
            'recentAudio' => MediaContent::with('category')
                ->whereIn('category_id', Category::whereIn('slug', ['news', 'health', 'economyfinance'])->select('id'))
                ->latest()->latest('id')
                ->take(8)
                ->get(),
            'topMusic' => MediaContent::with('artists')
                ->where('category_id', Category::where('slug', 'song')->value('id'))
                ->orderByDesc('play_count')
                ->take(5)
                ->get(),
            'countries' => $this->usersByCountry(),
        ];
    }

    /** @return Collection<string, int> ISO country code => number of users last seen there, biggest first */
    private function usersByCountry(): Collection
    {
        return DB::table('users')
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->pluck('total', 'country')
            ->map(fn ($total) => (int) $total);
    }
}
