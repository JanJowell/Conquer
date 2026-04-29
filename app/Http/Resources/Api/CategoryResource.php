<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registeredCount = $this->whenCounted('registrations');

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'distance_km' => $this->distance_km !== null ? (float) $this->distance_km : null,
            'description' => $this->description,
            'slot_limit' => $this->slot_limit,
            'registered_count' => $registeredCount,
            'slots_remaining' => is_int($registeredCount) && $this->slot_limit !== null
                ? max($this->slot_limit - $registeredCount, 0)
                : null,
            'status' => $this->status,
        ];
    }
}
