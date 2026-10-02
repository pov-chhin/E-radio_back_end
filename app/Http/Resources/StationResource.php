<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'stream_url' => $this->stream_url,
            'cover_url' => $this->coverUrl,
            'bitrate' => $this->bitrate,
            'votes' => $this->votes,
            'tags' => $this->tags
                ? array_values(array_filter(array_map('trim', explode(',', $this->tags))))
                : [],
        ];
    }
}
