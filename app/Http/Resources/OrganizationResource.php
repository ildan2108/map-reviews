<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'yandex_url' => $this->yandex_url,
            'parsing_status' => $this->parsing_status,
            'parsing_error' => $this->parsing_error,
            'parsing_started_at' => $this->parsing_started_at?->toISOString(),
            'parsed_at' => $this->parsed_at?->toISOString(),
            'name' => $this->name,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
        ];
    }
}
