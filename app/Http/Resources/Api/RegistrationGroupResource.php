<?php

namespace App\Http\Resources\Api;

use App\Services\GroupRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $members = $this->relationLoaded('activeRegistrations')
            ? $this->activeRegistrations
            : $this->activeRegistrations()->with('user')->get();
        $minimum = max((int) ($this->category?->group_min_members ?? 2), 2);
        $permissions = app(GroupRegistrationService::class)->permissionsFor($this->resource, $request->user());

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'status' => $this->status,
            'leader_user_id' => $this->leader_user_id,
            'is_leader' => $request->user()?->id === $this->leader_user_id,
            'member_count' => $members->count(),
            'minimum_members' => $minimum,
            'maximum_members' => $this->category?->group_max_members,
            'members_needed' => max($minimum - $members->count(), 0),
            'is_ready' => $this->status === 'locked'
                || ($this->status === 'ready' && $members->count() >= $minimum),
            ...$permissions,
            'locked_at' => optional($this->locked_at)?->toISOString(),
            'expires_at' => optional($this->expires_at)?->toISOString(),
            'members' => $members->map(fn ($registration) => [
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'name' => $registration->user?->name,
                'is_leader' => $registration->user_id === $this->leader_user_id,
                'registration_status' => $registration->status,
                'payment_status' => $registration->payment_status,
            ])->values(),
        ];
    }
}
