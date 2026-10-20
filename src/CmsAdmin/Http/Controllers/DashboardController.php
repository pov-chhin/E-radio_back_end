<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AdminCache;
use App\Support\AvatarUrl;
use Carbon\Carbon;
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
    /** "Online" means active in the last few minutes, not merely holding an unexpired session/token. */
    private const ONLINE_WINDOW_MINUTES = 5;

    /** How many online people the dashboard lists by name; the stat card still counts everyone. */
    private const ONLINE_LIST_LIMIT = 6;

    /** The counts and lists change slowly, so a minute-old dashboard is fine and saves about nine queries a load. */
    private const OVERVIEW_CACHE_SECONDS = 60;

    /** "Users online" is the one number an admin watches move, so it is cached only briefly. */
    private const ONLINE_CACHE_SECONDS = 15;

    public function index(): View
    {
        $overview = AdminCache::remember('dashboard:overview', self::OVERVIEW_CACHE_SECONDS, fn () => $this->overview());
        $online = AdminCache::remember('dashboard:online', self::ONLINE_CACHE_SECONDS, fn () => $this->onlineSnapshot());

        return view('cms-admin::dashboard', [
            'stats' => [...$overview['stats'], 'users_online' => $online['count']],
            // Not cached: an admin flips this on the settings page and expects the dashboard to follow at once.
            'scraperSetting' => RadioScraperSetting::first(),
            'recentChannels' => $overview['recentChannels'],
            'recentPodcasts' => $overview['recentPodcasts'],
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
            'topMusic' => MediaContent::with('artists')
                ->where('category_id', Category::where('slug', 'song')->value('id'))
                ->orderByDesc('play_count')
                ->take(5)
                ->get(),
            'countries' => $this->usersByCountry(),
        ];
    }

    /** @return array{count: int, users: Collection} everyone online, and the most recent few of them by name */
    private function onlineSnapshot(): array
    {
        $online = $this->onlineActivity();

        return ['count' => $online->count(), 'users' => $this->onlineUsers($online)];
    }

    /**
     * Admin-panel and web logins go through session auth (the `sessions` table); the app's Sanctum
     * tokens never create a session row, so those are read from `personal_access_tokens.last_used_at`
     * instead. A user active through either in the last few minutes counts once, at their latest activity.
     *
     * @return Collection<int, Carbon> user id => when they were last active
     */
    private function onlineActivity(): Collection
    {
        $since = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since->timestamp)
            ->get(['user_id', 'last_activity'])
            ->map(fn ($row) => [(int) $row->user_id, Carbon::createFromTimestamp($row->last_activity)]);

        $tokens = DB::table('personal_access_tokens')
            ->where('last_used_at', '>=', $since)
            ->get(['tokenable_id', 'last_used_at'])
            ->map(fn ($row) => [(int) $row->tokenable_id, Carbon::parse($row->last_used_at, config('app.timezone'))]);

        return $sessions->concat($tokens)
            ->groupBy(0)
            ->map(fn (Collection $pairs) => $pairs->pluck(1)->max());
    }

    /** The most recently active online people, with what the "Users online" card needs to show them. */
    private function onlineUsers(Collection $online): Collection
    {
        $latest = $online->sortByDesc(fn (Carbon $at) => $at->timestamp)->take(self::ONLINE_LIST_LIMIT);

        return DB::table('users')
            ->whereIn('id', $latest->keys())
            ->get(['id', 'name', 'first_name', 'last_name', 'username', 'email', 'avatar_url', 'type'])
            ->map(function ($user) use ($latest) {
                $user->display_name = trim($user->first_name.' '.$user->last_name) ?: ($user->name ?: ($user->username ?: $user->email));
                $user->avatar_url = AvatarUrl::from($user->avatar_url);
                $user->last_active = $latest->get((int) $user->id);

                return $user;
            })
            ->sortByDesc(fn ($user) => $user->last_active->timestamp)
            ->values();
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
