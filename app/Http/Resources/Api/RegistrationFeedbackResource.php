<?php

namespace App\Http\Resources\Api;

use App\Models\RegistrationFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'overall_rating' => $this->overall_rating,
            'ratings' => [
                'organization' => $this->organization_rating,
                'route' => $this->route_rating,
                'safety' => $this->safety_rating,
                'experience' => $this->experience_rating,
            ],
            'comment' => $this->comment,
            'submitted_at' => optional($this->submitted_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
            'editable_until' => optional($this->editableUntil())?->toISOString(),
            'can_edit' => $this->canEdit(),
            'edit_window_days' => RegistrationFeedback::EDIT_WINDOW_DAYS,
        ];
    }
}
