<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is online right now: the one calculation behind the dashboard's "All users online" card and the count beside the
 * bell in the admin top bar, so the two always show the same number.
 */
class OnlineUsers
{
    /** "Online" means active in the last few minutes, not merely holding an unexpired session/token. */
    private const WINDOW_MINUTES = 5;

    /** How many online people the dashboard lists by name; the count still includes everyone. */
    private const LIST_LIMIT = 6;

    /** "Users online" is the one number an admin watches move, so it is cached only briefly. */
    private const CACHE_SECONDS = 15;

    /** @return array{count: int, users: Collection} everyone online, and the most recent few of them by name */
    public static function snapshot(): array
    {
        return AdminCache::remember('dashboard:online', self::CACHE_SECONDS, function () {
            $online = self::activity();

            return ['count' => $online->count(), 'users' => self::mostRecent($online)];
        });
    }

    public static function count(): int
    {
        return self::snapshot()['count'];
    }

    /**
     * Admin-panel and web logins go through session auth (the `sessions` table); the app's Sanctum
     * tokens never create a session row, so those are read from `personal_access_tokens.last_used_at`
     * instead. A user active through either in the last few minutes counts once, at their latest activity.
     *
     * @return Collection<int, Carbon> user id => when they were last active
     */
    private static function activity(): Collection
    {
        $since = now()->subMinutes(self::WINDOW_MINUTES);

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
    private static function mostRecent(Collection $online): Collection
    {
        $latest = $online->sortByDesc(fn (Carbon $at) => $at->timestamp)->take(self::LIST_LIMIT);

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
}
