<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'rating' => $this->rating,
            'text' => $this->text,
            'published_at' => $this->published_at?->toISOString(),
            'organization_reply' => $this->organization_reply,
            'organization_reply_at' => $this->organization_reply_at?->toISOString(),
        ];
    }
}
