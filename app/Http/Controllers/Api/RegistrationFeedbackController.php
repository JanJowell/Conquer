<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RegistrationFeedbackResource;
use App\Models\Registration;
use App\Models\RegistrationFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationFeedbackController extends Controller
{
    public function show(Request $request, Registration $registration): JsonResponse
    {
        $this->authorizeParticipant($request, $registration);
        $registration->loadMissing(['raceResult', 'feedback']);

        return response()->json([
            'data' => $registration->feedback
                ? new RegistrationFeedbackResource($registration->feedback)
                : null,
            'eligibility' => $this->eligibility($registration),
        ]);
    }

    public function upsert(Request $request, Registration $registration): JsonResponse
    {
        $this->authorizeParticipant($request, $registration);
        $registration->loadMissing(['raceResult', 'feedback']);

        if (! $registration->raceResult) {
            return response()->json([
                'message' => 'Feedback is available after your official result has been recorded.',
            ], 422);
        }

        $feedback = $registration->feedback;

        if ($feedback && ! $feedback->canEdit()) {
            return response()->json([
                'message' => 'The seven-day feedback editing period has ended.',
            ], 422);
        }

        $validated = $request->validate([
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'organization_rating' => ['nullable', 'integer', 'between:1,5'],
            'route_rating' => ['nullable', 'integer', 'between:1,5'],
            'safety_rating' => ['nullable', 'integer', 'between:1,5'],
            'experience_rating' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasCreated = $feedback === null;
        $feedback ??= new RegistrationFeedback([
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'event_id' => $registration->event_id,
            'category_id' => $registration->category_id,
            'submitted_at' => now(),
        ]);
        $feedback->fill($validated)->save();

        return response()->json([
            'message' => $wasCreated ? 'Feedback submitted successfully.' : 'Feedback updated successfully.',
            'data' => new RegistrationFeedbackResource($feedback),
        ], $wasCreated ? 201 : 200);
    }

    private function authorizeParticipant(Request $request, Registration $registration): void
    {
        abort_unless((int) $registration->user_id === (int) $request->user()->id, 403);
    }

    private function eligibility(Registration $registration): array
    {
        $feedback = $registration->feedback;
        $hasResult = $registration->raceResult !== null;

        return [
            'has_result' => $hasResult,
            'has_submitted' => $feedback !== null,
            'can_submit' => $hasResult && $feedback === null,
            'can_edit' => $feedback?->canEdit() ?? false,
            'edit_window_days' => RegistrationFeedback::EDIT_WINDOW_DAYS,
            'reason' => ! $hasResult
                ? 'Feedback is available after your official result has been recorded.'
                : ($feedback && ! $feedback->canEdit()
                    ? 'The seven-day feedback editing period has ended.'
                    : null),
        ];
    }
}
