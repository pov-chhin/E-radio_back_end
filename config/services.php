<?php

return [

    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'probe_binary' => env('FFMPEG_PROBE_BINARY', 'ffprobe'),
        'hls_bitrate' => env('FFMPEG_HLS_BITRATE', '128k'),
        'hls_timeout' => env('FFMPEG_HLS_TIMEOUT', 300),
        'opus_bitrate' => env('FFMPEG_OPUS_BITRATE', '64k'),
        'opus_timeout' => env('FFMPEG_OPUS_TIMEOUT', 300),
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
    ],

];
