<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
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

    public function index(): View
    {
        return view('cms-admin::dashboard', [
            'stats' => [
                'channels_total' => RadioSceaperStation::count(),
                'channels_published' => RadioSceaperStation::where('is_published', true)->count(),
                'users_total' => DB::table('users')->count(),
                'users_online' => $this->onlineUserCount(),
            ],
            'scraperSetting' => RadioScraperSetting::first(),
            'recentChannels' => RadioSceaperStation::latest()->take(5)->get(),
            'recentPodcasts' => Program::with('type')->latest()->take(5)->get(),
            'topMusic' => MediaContent::with('artist')
                ->where('category_id', Category::where('slug', 'song')->value('id'))
                ->orderByDesc('play_count')
                ->take(10)
                ->get(),
        ]);
    }

    /**
     * Admin-panel and web logins go through session auth (the `sessions` table); the app's Sanctum
     * tokens never create a session row, so those are read from `personal_access_tokens.last_used_at`
     * instead. A user active through either in the last few minutes counts once.
     */
    private function onlineUserCount(): int
    {
        $since = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $sessionUserIds = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since->timestamp)
            ->pluck('user_id');

        $tokenUserIds = DB::table('personal_access_tokens')
            ->where('last_used_at', '>=', $since)
            ->pluck('tokenable_id');

        return $sessionUserIds->merge($tokenUserIds)->unique()->count();
    }
}
