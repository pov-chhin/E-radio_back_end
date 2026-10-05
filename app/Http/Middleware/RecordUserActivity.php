<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed-in app user "online" while they browse and listen.
 *
 * The dashboard's "Users online" reads `personal_access_tokens.last_used_at`, which Sanctum only stamps when a
 * request is authenticated by the token. Most of what the app does — stations, programs, songs, plays — is public,
 * so without this a user who is just listening would drop off the list after a few minutes. Resolving the Sanctum
 * user here (on any request that carries a token) makes Sanctum stamp it; a missing or invalid token is ignored.
 */
class RecordUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** Runs after the response has been sent, so it never slows an API call down. */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->bearerToken()) {
            $request->user('sanctum');
        }
    }
}
