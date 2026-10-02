<?php

namespace Src\RadioStream\Services;

use Src\RadioStream\Models\RadioStream;

class StreamService
{
    public function preferred(iterable $streams): ?RadioStream
    {
        return collect($streams)
            ->sortBy(fn (RadioStream $stream) => [$stream->health_status !== 'healthy', ! $stream->is_primary, $stream->priority])
            ->first();
    }

    public function healthLabel(string $status): string
    {
        return match ($status) {
            'healthy' => 'Operational',
            'degraded' => 'Degraded',
            default => 'Offline',
        };
    }
}
