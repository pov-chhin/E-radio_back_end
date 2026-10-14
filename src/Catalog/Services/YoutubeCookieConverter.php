<?php

namespace Src\Catalog\Services;

/**
 * Turns the JSON that browser extensions export (Cookie-Editor, EditThisCookie, "Get cookies.txt LOCALLY" in its
 * JSON mode …) into the Netscape cookies.txt that yt-dlp reads, so the admin can upload what the browser gave
 * without converting it by hand. Only YouTube and Google cookies are kept: a browser export holds every site the
 * person is signed in to, and none of the others should reach (or be stored on) the server.
 */
class YoutubeCookieConverter
{
    private const KEEP = ['youtube.com', 'google.com'];

    /** Whether an uploaded file is a JSON export rather than a Netscape cookies.txt. */
    public static function looksLikeJson(string $contents): bool
    {
        return in_array(substr(ltrim($contents), 0, 1), ['[', '{'], true);
    }

    /**
     * The Netscape text for the YouTube/Google cookies in a JSON export (a list of cookies, or an object with a
     * "cookies" list), or null when the text is not such an export. A list with no YouTube/Google cookie in it gives
     * a file with a header only, which the caller's own check then refuses.
     */
    public static function fromJson(string $json): ?string
    {
        $data = json_decode($json, true);

        if (is_array($data) && is_array($data['cookies'] ?? null)) {
            $data = $data['cookies'];
        }

        if (! is_array($data) || ! array_is_list($data)) {
            return null;
        }

        $lines = ['# Netscape HTTP Cookie File', '# Converted from a browser JSON export - YouTube and Google cookies only.', ''];

        foreach ($data as $cookie) {
            if (is_array($cookie) && self::text($cookie['name'] ?? null) !== '' && self::wanted(self::text($cookie['domain'] ?? null))) {
                $lines[] = self::row($cookie);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private static function wanted(string $domain): bool
    {
        $host = strtolower(ltrim($domain, '.'));

        foreach (self::KEEP as $keep) {
            if ($host === $keep || str_ends_with($host, '.'.$keep)) {
                return true;
            }
        }

        return false;
    }

    private static function row(array $cookie): string
    {
        $domain = self::text($cookie['domain'] ?? null);
        $hostOnly = ! empty($cookie['hostOnly']) && ! str_starts_with($domain, '.');

        if (! $hostOnly && ! str_starts_with($domain, '.')) {
            $domain = '.'.$domain;
        }

        $expires = $cookie['expirationDate'] ?? $cookie['expires'] ?? 0;
        $expires = ! empty($cookie['session']) || ! is_numeric($expires) || $expires < 0 ? 0 : (int) $expires;

        return implode("\t", [
            (! empty($cookie['httpOnly']) ? '#HttpOnly_' : '').$domain,
            $hostOnly ? 'FALSE' : 'TRUE',
            self::text($cookie['path'] ?? null) ?: '/',
            ! empty($cookie['secure']) ? 'TRUE' : 'FALSE',
            (string) $expires,
            self::text($cookie['name'] ?? null),
            self::text($cookie['value'] ?? null),
        ]);
    }

    /** A cell of the file: tabs and line breaks would break its columns. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? str_replace(["\t", "\r", "\n"], ' ', (string) $value) : '';
    }
}
