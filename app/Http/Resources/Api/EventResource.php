<?php

namespace App\Http\Resources\Api;

use App\Services\CategoryRegistrationEligibility;
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
        $currentRegistrations = $this->relationLoaded('currentUserRegistrations')
            ? $this->currentUserRegistrations
            : collect($this->relationLoaded('currentUserRegistration') && $this->currentUserRegistration
                ? [$this->currentUserRegistration]
                : []);
        $currentRegistration = $currentRegistrations->first();
        $hasRegistrationState = $this->relationLoaded('currentUserRegistrations')
            || $this->relationLoaded('currentUserRegistration');
        $eligibilityService = app(CategoryRegistrationEligibility::class);
        $categoryRegistrationStates = $this->relationLoaded('categories')
            ? $this->categories->map(function ($category) use ($currentRegistrations, $eligibilityService) {
                $registration = $currentRegistrations->firstWhere('category_id', $category->id);
                $hasBlockingRegistration = $registration && $registration->status !== 'rejected';
                $eligibility = $hasBlockingRegistration
                    ? [
                        'allowed' => false,
                        'reason' => 'You are already registered for this category.',
                        'conflicting_registration' => $registration,
                    ]
                    : $eligibilityService->evaluate($category, $currentRegistrations);

                return [
                    'category_id' => $category->id,
                    'is_registered' => $registration !== null,
                    'registration_id' => $registration?->id,
                    'registration_status' => $registration?->status,
                    'can_register' => $eligibility['allowed'],
                    'conflict_reason' => $eligibility['reason'],
                    'conflict_category_id' => $eligibility['conflicting_registration']?->category_id,
                ];
            })->values()
            : collect();
        $canRegister = $status === 'upcoming'
            && ! $registrationDeadlinePassed
            && $hasOpenCategory
            && ($categoryRegistrationStates->isEmpty() || $categoryRegistrationStates->contains('can_register', true));

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue,
            'event_date' => optional($this->event_date)->format('Y-m-d'),
            'event_start_date' => optional($this->event_date)->format('Y-m-d'),
            'event_end_date' => optional($this->event_end_date ?? $this->event_date)->format('Y-m-d'),
            'start_time' => optional($this->start_time)->format('H:i'),
            'end_time' => optional($this->end_time)->format('H:i'),
            'registration_deadline' => optional($this->registration_deadline)->format('Y-m-d'),
            'status' => $status,
            'can_register' => $canRegister,
            'registration_deadline_passed' => $registrationDeadlinePassed,
            'banner_url' => $this->banner_image ? asset('storage/'.$this->banner_image) : null,
            'organized_by' => $this->organized_by,
            'interest_type' => $this->interest_type,
            'category_label' => $this->categorySectionLabel(),
            'type_details' => $this->type_details,
            'type_detail_items' => $this->formattedTypeDetails(),
            'participants_count' => $this->when(
                isset($this->participants_count),
                (int) ($this->participants_count ?? 0)
            ),
            'registration_entries_count' => $this->whenCounted('registrations'),
            'is_registered' => $hasRegistrationState
                ? $currentRegistration !== null
                : false,
            'registration_status' => $hasRegistrationState && $currentRegistration
                ? $currentRegistration->status
                : null,
            'registration_rejection_reason' => $hasRegistrationState && $currentRegistration
                ? $currentRegistration->rejection_reason
                : null,
            'registered_category_id' => $hasRegistrationState && $currentRegistration
                ? $currentRegistration->category_id
                : null,
            'registered_category_ids' => $currentRegistrations->pluck('category_id')->unique()->values(),
            'active_registered_category_ids' => $currentRegistrations
                ->where('status', '!=', 'rejected')
                ->pluck('category_id')
                ->unique()
                ->values(),
            'current_registration' => $hasRegistrationState
                ? new RegistrationResource($currentRegistration)
                : null,
            'current_registrations' => RegistrationResource::collection($this->whenLoaded('currentUserRegistrations')),
            'category_registration_states' => $categoryRegistrationStates,
            'category_registration_buffer_minutes' => CategoryRegistrationEligibility::SAFETY_BUFFER_MINUTES,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'announcements' => AnnouncementResource::collection($this->whenLoaded('announcements')),
        ];
    }
}
