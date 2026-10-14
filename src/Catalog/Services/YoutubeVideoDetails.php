<?php

namespace Src\Catalog\Services;

use DateInterval;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads what the YouTube Data API v3 knows about a video — its title, length and cover — so the episode form can
 * fill itself in from a pasted link. The API carries no audio (and its terms rule out downloading): the audio still
 * comes from {@see YoutubeAudioFetcher}. A lookup costs 1 of the project's 10,000 daily quota units.
 */
class YoutubeVideoDetails
{
    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';

    /** Biggest first: not every video has the larger covers. */
    private const THUMBNAILS = ['maxres', 'standard', 'high', 'medium', 'default'];

    public static function configured(): bool
    {
        return filled(config('services.youtube_data.key'));
    }

    /**
     * Throws a RuntimeException whose message is fit to show an admin (never one that contains the API key).
     *
     * @return array{title: string, channel: ?string, duration_seconds: ?int, thumbnail_url: ?string, is_live: bool}
     */
    public function fetch(string $url): array
    {
        $videoId = YoutubeAudioFetcher::videoId($url) ?? throw new RuntimeException(__('That is not a YouTube video link.'));

        if (! self::configured()) {
            throw new RuntimeException(__('Set YOUTUBE_API_KEY on the server (a YouTube Data API v3 key) to fill these in from the link.'));
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.youtube_data.timeout', 10))
                ->get(self::ENDPOINT, [
                    'part' => 'snippet,contentDetails',
                    'id' => $videoId,
                    'key' => config('services.youtube_data.key'),
                ]);
        } catch (ConnectionException) {
            // The exception's message carries the request address — and so the key — so it is never passed on.
            throw new RuntimeException(__('Could not reach YouTube. Try again in a moment.'));
        }

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $video = $response->json('items.0');

        if (! is_array($video) || trim((string) data_get($video, 'snippet.title')) === '') {
            throw new RuntimeException(__('YouTube has no public video at that link (it may be private or deleted).'));
        }

        return [
            'title' => trim((string) data_get($video, 'snippet.title')),
            'channel' => data_get($video, 'snippet.channelTitle') ?: null,
            'duration_seconds' => self::seconds(data_get($video, 'contentDetails.duration')),
            'thumbnail_url' => self::thumbnail((array) data_get($video, 'snippet.thumbnails', [])),
            // A live or upcoming broadcast has no fixed length, and the server can't fetch the audio of a live stream.
            'is_live' => (data_get($video, 'snippet.liveBroadcastContent') ?? 'none') !== 'none',
        ];
    }

    /** The seconds in an ISO 8601 duration such as "PT1H2M3S"; null when it is empty or zero (a live broadcast is "P0D"). */
    public static function seconds(mixed $duration): ?int
    {
        if (! is_string($duration) || $duration === '') {
            return null;
        }

        try {
            $interval = new DateInterval($duration);
        } catch (Exception) {
            return null;
        }

        $seconds = (($interval->d * 24 + $interval->h) * 60 + $interval->i) * 60 + $interval->s;

        return $seconds > 0 ? $seconds : null;
    }

    /** The address of the largest cover the video has. */
    private static function thumbnail(array $thumbnails): ?string
    {
        foreach (self::THUMBNAILS as $size) {
            $url = $thumbnails[$size]['url'] ?? null;

            if (is_string($url) && str_starts_with($url, 'https://')) {
                return $url;
            }
        }

        return null;
    }

    private function errorMessage(Response $response): string
    {
        // Older errors name their reason under error.errors, newer ones under error.details.
        $reasons = array_merge(
            (array) $response->json('error.errors.*.reason'),
            (array) $response->json('error.details.*.reason'),
        );
        $is = fn (array $codes): bool => array_intersect($codes, $reasons) !== [];

        return match (true) {
            $is(['quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded', 'userRateLimitExceeded', 'RATE_LIMIT_EXCEEDED'])
                => __('The YouTube API quota for today is used up. Try again tomorrow, or type the details in by hand.'),
            $is(['keyInvalid', 'API_KEY_INVALID'])
                => __('The YouTube API key is not valid. Check YOUTUBE_API_KEY on the server.'),
            $is(['accessNotConfigured', 'SERVICE_DISABLED'])
                => __('YouTube Data API v3 is not turned on for that key\'s Google Cloud project.'),
            $is(['ipRefererBlocked', 'API_KEY_HTTP_REFERRER_BLOCKED', 'API_KEY_IP_ADDRESS_BLOCKED', 'API_KEY_SERVICE_BLOCKED'])
                => __('The YouTube API key is restricted and does not allow this server. Allow its IP address, or remove the restriction.'),
            default => __('YouTube answered with an error (HTTP :status).', ['status' => $response->status()]),
        };
    }
}
