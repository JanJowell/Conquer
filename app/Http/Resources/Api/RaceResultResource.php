<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RaceResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'finish_time' => $this->finish_time,
            'rank_overall' => $this->rank_overall,
            'rank_category' => $this->rank_category,
            'remarks' => $this->remarks,
            'event' => new EventResource($this->whenLoaded('event')),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
