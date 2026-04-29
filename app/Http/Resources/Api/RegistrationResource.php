<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bib_number' => $this->bib_number,
            'shirt_size' => $this->shirt_size,
            'medical_conditions' => $this->medical_conditions,
            'status' => $this->status,
            'registered_at' => optional($this->registered_at)?->toISOString(),
            'event' => new EventResource($this->whenLoaded('event')),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
