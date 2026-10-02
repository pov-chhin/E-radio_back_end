<?php

namespace Src\RadioStream\Supports;

class DemoRadioData
{
    public function get(): array
    {
        return [
            'station' => [
                'name' => 'Pulse FM', 'frequency' => '97.8 FM', 'tagline' => 'The sound of your city',
                'listeners' => 2486, 'peak' => 3214, 'uptime' => '99.98%', 'latency' => '1.8s',
                'now_playing' => 'Midnight City', 'artist' => 'M83', 'show' => 'Drive Time with Maya',
                'started_at' => '16:02', 'ends_at' => '18:00', 'source' => 'Live studio', 'art' => null,
                'stream_url' => 'https://icecast.radiofrance.fr/fip-midfi.mp3',
            ],
            'stations' => [
                ['name' => 'Pulse FM', 'freq' => '97.8', 'genre' => 'Pop · Hits', 'listeners' => 2486, 'status' => 'Live', 'color' => '#ff4d67'],
                ['name' => 'Echo Jazz', 'freq' => '101.2', 'genre' => 'Jazz · Soul', 'listeners' => 864, 'status' => 'AutoDJ', 'color' => '#8a65ff'],
                ['name' => 'City Talk', 'freq' => 'AM 860', 'genre' => 'News · Talk', 'listeners' => 1208, 'status' => 'Live', 'color' => '#28c6a0'],
                ['name' => 'Neon Nights', 'freq' => 'DAB+', 'genre' => 'Electronic', 'listeners' => 631, 'status' => 'AutoDJ', 'color' => '#ffb84d'],
            ],
            'tracks' => [
                ['id' => 1, 'title' => 'Midnight City', 'artist' => 'M83', 'album' => 'Hurry Up, We’re Dreaming', 'duration' => '4:03', 'plays' => 184, 'color' => '#fa4762'],
                ['id' => 2, 'title' => 'Electric Feel', 'artist' => 'MGMT', 'album' => 'Oracular Spectacular', 'duration' => '3:49', 'plays' => 143, 'color' => '#8165e8'],
                ['id' => 3, 'title' => 'Dreams', 'artist' => 'Fleetwood Mac', 'album' => 'Rumours', 'duration' => '4:17', 'plays' => 136, 'color' => '#26b994'],
                ['id' => 4, 'title' => 'Sunset Lover', 'artist' => 'Petit Biscuit', 'album' => 'Presence', 'duration' => '3:58', 'plays' => 121, 'color' => '#f3a942'],
                ['id' => 5, 'title' => 'The Less I Know the Better', 'artist' => 'Tame Impala', 'album' => 'Currents', 'duration' => '3:36', 'plays' => 117, 'color' => '#3b8ef3'],
            ],
            'schedule' => [
                ['time' => '06:00', 'end' => '09:00', 'title' => 'Morning Pulse', 'host' => 'Alex Rivera', 'type' => 'Live', 'color' => '#ff4d67'],
                ['time' => '09:00', 'end' => '12:00', 'title' => 'Workday Mix', 'host' => 'AutoDJ', 'type' => 'Playlist', 'color' => '#8a65ff'],
                ['time' => '12:00', 'end' => '14:00', 'title' => 'Lunch Break', 'host' => 'Sam Chen', 'type' => 'Live', 'color' => '#28c6a0'],
                ['time' => '14:00', 'end' => '16:00', 'title' => 'Afternoon Flow', 'host' => 'AutoDJ', 'type' => 'Playlist', 'color' => '#f3a942'],
                ['time' => '16:00', 'end' => '18:00', 'title' => 'Drive Time', 'host' => 'Maya Brooks', 'type' => 'Live now', 'color' => '#ff4d67'],
                ['time' => '18:00', 'end' => '21:00', 'title' => 'Night Shift', 'host' => 'Jordan Lee', 'type' => 'Live', 'color' => '#3b8ef3'],
            ],
            'regions' => [
                ['name' => 'Cambodia', 'value' => 38, 'count' => 945], ['name' => 'United States', 'value' => 24, 'count' => 597],
                ['name' => 'France', 'value' => 16, 'count' => 398], ['name' => 'Japan', 'value' => 12, 'count' => 298],
                ['name' => 'Other', 'value' => 10, 'count' => 248],
            ],
            'activity' => [
                ['title' => 'Live source connected', 'meta' => 'Studio A · 4 minutes ago', 'tone' => 'green'],
                ['title' => 'Peak listener milestone', 'meta' => '3,000 concurrent listeners · 38 minutes ago', 'tone' => 'violet'],
                ['title' => 'Playlist synchronized', 'meta' => 'Workday Mix · 1 hour ago', 'tone' => 'blue'],
                ['title' => 'Relay recovered', 'meta' => 'Singapore edge · 2 hours ago', 'tone' => 'amber'],
            ],
            'chart' => [820, 760, 980, 1140, 1060, 1420, 1310, 1680, 1850, 1730, 2180, 2060, 2486],
        ];
    }
}
