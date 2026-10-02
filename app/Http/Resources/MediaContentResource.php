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
            'artist_name' => $this->whenLoaded('artist', fn () => $this->artist?->name),
            'artist_photo_url' => $this->whenLoaded('artist', fn () => $this->artist?->photoUrl),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
