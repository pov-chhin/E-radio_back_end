<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'media_type' => $this->media_type,
            'stream_url' => $this->streamUrl,
            'processing_status' => $this->processing_status,
            'thumbnail_url' => $this->thumbnailUrl,
            'duration_seconds' => $this->duration_seconds,
            'play_count' => $this->play_count,
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Everyone credited: the names joined ("A, B"), the first one's photo, and the full list.
            'artist_name' => $this->whenLoaded('artists', fn () => $this->artists->pluck('name')->implode(', ') ?: null),
            'artist_photo_url' => $this->whenLoaded('artists', fn () => $this->artists->first()?->photoUrl),
            'artists' => $this->whenLoaded('artists', fn () => $this->artists->map(fn ($artist) => [
                'id' => (string) $artist->id,
                'name' => $artist->name,
                'photo_url' => $artist->photoUrl,
            ])->values()),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
