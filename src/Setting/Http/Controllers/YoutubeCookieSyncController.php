<?php

namespace Src\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Catalog\Services\YoutubeCookieConverter;
use Src\Catalog\Services\YoutubeCookieStore;

/**
 * The e-Radio cookie-sync browser extension posts the YouTube and Google cookies of a signed-in browser here every
 * few hours, so the server's yt-dlp keeps working without anyone exporting and uploading a file.
 *
 * It is switched on by YOUTUBE_COOKIE_SYNC_TOKEN (at least 32 characters; unset, the address does not exist) and
 * answers only a request that carries that token in X-Cookie-Sync-Token. It never sends the stored cookies back.
 */
class YoutubeCookieSyncController extends Controller
{
    private const MAX_BYTES = 1_000_000;

    public function __invoke(Request $request, YoutubeCookieStore $store): JsonResponse
    {
        $expected = (string) config('services.youtube_cookie_sync.token');

        abort_if(strlen($expected) < 32, 404);

        if (! hash_equals($expected, (string) $request->header('X-Cookie-Sync-Token'))) {
            return response()->json(['message' => 'That token is not accepted.'], 401);
        }

        $body = $request->getContent();

        if (strlen($body) > self::MAX_BYTES) {
            return response()->json(['message' => 'That is too much data for a cookie list.'], 413);
        }

        // The extension sends the same JSON shape the browser exports: {"cookies": [...]}. Only YouTube and Google
        // cookies survive the conversion, whatever the sender put in.
        $netscape = YoutubeCookieConverter::fromJson($body);

        if ($netscape === null || ! YoutubeCookieStore::valid($netscape)) {
            return response()->json(['message' => 'There are no YouTube cookies in that request.'], 422);
        }

        return response()->json([
            'cookies' => YoutubeCookieStore::count($netscape),
            'retried' => $store->save($netscape),
        ]);
    }
}
