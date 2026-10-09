<?php

return [

    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'probe_binary' => env('FFMPEG_PROBE_BINARY', 'ffprobe'),
        'hls_bitrate' => env('FFMPEG_HLS_BITRATE', '128k'),
        'hls_timeout' => env('FFMPEG_HLS_TIMEOUT', 300),
        'opus_bitrate' => env('FFMPEG_OPUS_BITRATE', '64k'),
        'opus_timeout' => env('FFMPEG_OPUS_TIMEOUT', 300),
        // Episodes whose audio comes from a YouTube link: small mono Opus (clear for speech, light on mobile data).
        'youtube_bitrate' => env('FFMPEG_YOUTUBE_BITRATE', '24k'),
        'youtube_channels' => (int) env('FFMPEG_YOUTUBE_CHANNELS', 1),
    ],

    // Fetches the audio track of a YouTube link (see Src\Catalog\Services\YoutubeAudioFetcher).
    'yt_dlp' => [
        'binary' => env('YT_DLP_BINARY', 'yt-dlp'),
        'timeout' => (int) env('YT_DLP_TIMEOUT', 300),
        // Tries per video: YouTube now and then refuses a request (403) that works a moment later.
        'attempts' => (int) env('YT_DLP_ATTEMPTS', 3),
        'max_filesize' => env('YT_DLP_MAX_FILESIZE', '300M'),
        // YouTube answers 403 unless yt-dlp can run its JavaScript challenge: have Deno or Node installed.
        'js_runtimes' => array_values(array_filter(array_map('trim', explode(',', (string) env('YT_DLP_JS_RUNTIMES', 'deno,node'))))),
        // Optional cookies.txt (Netscape format) for when YouTube asks the server to sign in. Keep it outside public/.
        'cookies' => env('YT_DLP_COOKIES') ?: storage_path('app/youtube/cookies.txt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // The OAuth client ID(s) the mobile app asks Google to issue ID tokens
        // for (the "Web application" client, which the app passes as its server
        // client ID). Comma-separated; a token for any other client is refused,
        // and with none set "Continue with Google" is switched off.
        'client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))
        ))),
    ],

    'azuracast' => [
        'url' => env('AZURACAST_URL'),
        'api_key' => env('AZURACAST_API_KEY'),
        'station_id' => env('AZURACAST_STATION_ID', 'azuratest'),
        'timeout' => env('AZURACAST_TIMEOUT', 5),
    ],

    'radio_browser' => [
        'enabled' => env('RADIO_BROWSER_ENABLED', false),
        'country' => env('RADIO_BROWSER_COUNTRY', 'KH'),
    ],

    'media_storage' => [
        'image_directory' => env('MEDIA_IMAGE_DIRECTORY', 'media-contents/thumbnails'),
        'audio_directory' => env('MEDIA_AUDIO_DIRECTORY', 'media-contents/streams'),
        // The disk new uploads go to — 'public' (local) or 'media_s3'. Settings → Storage overrides it from the database.
        'disk' => 'public',
    ],

    // Defaults the RustFS connection (Settings → RustFS) is created with; the admin only adds the access keys.
    'rustfs' => [
        'endpoint' => env('RUSTFS_ENDPOINT', 'http://s3.devams.site'),
        'bucket' => env('RUSTFS_BUCKET', 'rustfs'),
        'region' => env('RUSTFS_REGION', 'us-east-1'),
        // Used while no keys are saved in Settings → RustFS, so the admin does not have to type them in.
        'key' => env('RUSTFS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('RUSTFS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        // The address files are read from, used while no Public URL is saved in Settings → RustFS.
        'url' => env('RUSTFS_URL'),
    ],

];
