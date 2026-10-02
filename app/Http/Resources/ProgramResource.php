<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A show (podcast) as the mobile app lists and opens it. */
class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('latestEpisode') ? $this->latestEpisode : null;

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url ?? $latest?->thumbnail_url,
            'category' => new CategoryResource($this->whenLoaded('category')),
            // The podcast type (a tag under Podcast), if the program has one.
            'type' => $this->whenLoaded('type', fn () => $this->type ? [
                'id' => (string) $this->type->id,
                'name' => $this->type->name,
                'slug' => $this->type->slug,
            ] : null),
            // Programs have no host of their own — the newest episode's artist stands in.
            'host_name' => $latest?->artist?->name,
            'episode_count' => $this->whenCounted('episodes'),
            'episodes' => MediaContentResource::collection($this->whenLoaded('episodes')),
        ];
    }
}
