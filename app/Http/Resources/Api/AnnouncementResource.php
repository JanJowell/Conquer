<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'content' => $this->content,
            'is_auto_generated' => $this->is_auto_generated,
            'published_at' => optional($this->published_at ?? $this->created_at)?->toISOString(),
            'expires_at' => optional($this->expires_at)?->toISOString(),
            'is_expired' => $this->is_expired,
            'action' => $this->event_id ? [
                'type' => 'event_detail',
                'label' => 'View Event',
                'event_id' => $this->event_id,
                'api_url' => url("/api/events/{$this->event_id}"),
            ] : null,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
            ]),
        ];
    }
}
