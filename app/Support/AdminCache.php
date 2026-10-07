<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Predis\PredisException;
use RedisException;

class AdminCache
{
    /**
     * Cache::remember() for the admin panel: a hit comes from the cache store (Redis), a miss runs $callback
     * against MySQL and stores the result for $seconds.
     *
     * If Redis fails (refuses the connection, drops it, errors) the callback just runs, so a cache outage makes
     * the panel slower instead of broken. Only the Redis clients' own exceptions are caught: an error inside
     * $callback (a bad query, say) still surfaces. $callback should be a plain read, because in the rare case
     * Redis drops between the read and the write it runs a second time.
     */
    public static function remember(string $key, int $seconds, Closure $callback): mixed
    {
        try {
            return Cache::remember("admin:{$key}", $seconds, $callback);
        } catch (PredisException|RedisException $e) {
            Log::warning('Admin cache unavailable, reading from the database instead.', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }
}
