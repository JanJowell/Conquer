<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->effective_status;
        $registrationDeadlinePassed = $this->registration_deadline
            ? $this->registration_deadline->isBefore(today())
            : false;
        $hasOpenCategory = $this->relationLoaded('categories')
            ? $this->categories->isNotEmpty()
            : $this->categories()->where('status', 'open')->exists();
        $currentRegistration = $this->relationLoaded('currentUserRegistration')
            ? $this->currentUserRegistration
            : null;
        $hasBlockingRegistration = $currentRegistration && $currentRegistration->status !== 'rejected';
        $canRegister = $status === 'upcoming'
            && ! $registrationDeadlinePassed
            && $hasOpenCategory
            && ! $hasBlockingRegistration;

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
            'status' => $status,
            'can_register' => $canRegister,
            'registration_deadline_passed' => $registrationDeadlinePassed,
            'banner_url' => $this->banner_image ? asset('storage/'.$this->banner_image) : null,
            'organized_by' => $this->organized_by,
            'interest_type' => $this->interest_type,
            'participants_count' => $this->whenCounted('registrations'),
            'is_registered' => $this->relationLoaded('currentUserRegistration')
                ? $this->currentUserRegistration !== null
                : false,
            'registration_status' => $this->relationLoaded('currentUserRegistration') && $this->currentUserRegistration
                ? $this->currentUserRegistration->status
                : null,
            'registration_rejection_reason' => $this->relationLoaded('currentUserRegistration') && $this->currentUserRegistration
                ? $this->currentUserRegistration->rejection_reason
                : null,
            'registered_category_id' => $this->relationLoaded('currentUserRegistration') && $this->currentUserRegistration
                ? $this->currentUserRegistration->category_id
                : null,
            'current_registration' => new RegistrationResource($this->whenLoaded('currentUserRegistration')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'announcements' => AnnouncementResource::collection($this->whenLoaded('announcements')),
        ];
    }
}
