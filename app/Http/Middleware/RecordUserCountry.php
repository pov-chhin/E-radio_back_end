<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers which country an authenticated API user is connecting from, for the dashboard's "Visitors by country" map.
 *
 * The country comes from the header the CDN / reverse proxy in front of the app adds (Cloudflare's CF-IPCountry and
 * the like) — nothing is looked up and no IP address is stored. Without such a header the user's country stays empty.
 */
class RecordUserCountry
{
    /** Headers that carry the visitor's ISO country code, in order of preference. */
    private const HEADERS = ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'X-AppEngine-Country', 'X-Country-Code'];

    /** Placeholders CDNs send when the country is unknown, anonymised (Tor) or otherwise not a real country. */
    private const NOT_A_COUNTRY = ['XX', 'ZZ', 'T1', 'A1', 'A2', 'O1'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** Runs after the response has been sent, so it never slows an API call down. */
    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        $country = $this->countryFrom($request);

        if (! $user || ! $country || $user->country === $country) {
            return;
        }

        // Best-effort telemetry: a failed write must never surface as an API error.
        rescue(fn () => DB::table('users')->where('id', $user->getKey())->update(['country' => $country]));
    }

    private function countryFrom(Request $request): ?string
    {
        foreach (self::HEADERS as $header) {
            $value = strtoupper(trim((string) $request->header($header)));

            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, self::NOT_A_COUNTRY, true)) {
                return $value;
            }
        }

        return null;
    }
}
