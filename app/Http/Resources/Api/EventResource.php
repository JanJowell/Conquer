<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue,
            'event_date' => optional($this->event_date)->format('Y-m-d'),
            'start_time' => optional($this->start_time)->format('H:i'),
            'end_time' => optional($this->end_time)->format('H:i'),
            'registration_deadline' => optional($this->registration_deadline)->format('Y-m-d'),
            'status' => $this->effective_status,
            'banner_url' => $this->banner_image ? asset('storage/'.$this->banner_image) : null,
            'organized_by' => $this->organized_by,
            'interest_type' => $this->interest_type,
            'participants_count' => $this->whenCounted('registrations'),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'announcements' => AnnouncementResource::collection($this->whenLoaded('announcements')),
        ];
    }
}
