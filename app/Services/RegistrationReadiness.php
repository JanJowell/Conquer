<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;

class RegistrationReadiness
{
    public function for(Registration $registration): array
    {
        $registration->loadMissing([
            'event',
            'category.event',
            'raceResult',
            'issuedEBadges.badge',
        ]);

        $category = $registration->category;
        $result = $registration->raceResult;
        $issuedBadges = $registration->issuedEBadges;
        $registrationStatus = (string) $registration->status;
        $isRejected = $registrationStatus === 'rejected';
        $isApproved = in_array($registrationStatus, ['approved', 'checked_in', 'completed'], true);
        $isCheckedIn = in_array($registrationStatus, ['checked_in', 'completed'], true);
        $paymentRequired = (bool) $registration->payment_required;
        $paymentComplete = ! $paymentRequired
            || in_array($registration->payment_status, [Payment::STATUS_PAID, Payment::STATUS_WAIVED], true);
        $paymentWaiting = $paymentRequired
            && in_array($registration->payment_status, [Payment::STATUS_PENDING, Payment::STATUS_SUBMITTED], true);
        $waiverComplete = (bool) $registration->waiver_accepted || $registration->kit_waiver_signed_at !== null;
        $medicalCertificateRequired = $category?->requiresMedicalCertificate() ?? false;
        $medicalCertificateComplete = ! $medicalCertificateRequired
            || filled($registration->medical_certificate_path)
            || $registration->medical_certificate_submitted_at !== null;
        $firstAidComplete = (bool) $registration->first_aid_kit_confirmed;
        $bibAssigned = filled($registration->bib_number);
        $raceKitReleased = $registration->kit_released_at !== null;
        $participantRequirementsComplete = $paymentComplete
            && $waiverComplete
            && $medicalCertificateComplete
            && $firstAidComplete;
        $readyForEventDay = ! $isRejected
            && $isApproved
            && $bibAssigned
            && $participantRequirementsComplete;
        $categoryDetails = $category?->resolvedTypeDetails() ?? [];
        $gearKey = collect(['required_gear', 'mandatory_gear'])
            ->first(fn (string $key) => filled($categoryDetails[$key] ?? null));

        return [
            'overall_status' => $this->overallStatus(
                $registrationStatus,
                $paymentWaiting,
                $participantRequirementsComplete,
                $bibAssigned,
                $result !== null
            ),
            'next_action' => $this->nextAction(
                $registration,
                $isRejected,
                $isApproved,
                $isCheckedIn,
                $paymentComplete,
                $paymentWaiting,
                $waiverComplete,
                $medicalCertificateComplete,
                $firstAidComplete,
                $bibAssigned,
                $raceKitReleased,
                $result !== null
            ),
            'is_ready_for_event_day' => $readyForEventDay,
            'steps' => [
                'approval' => [
                    'required' => true,
                    'completed' => $isApproved,
                    'status' => $isRejected ? 'failed' : ($isApproved ? 'complete' : 'pending'),
                ],
                'payment' => [
                    'required' => $paymentRequired,
                    'completed' => $paymentComplete,
                    'status' => ! $paymentRequired
                        ? 'not_required'
                        : ($paymentComplete ? 'complete' : ($paymentWaiting ? 'pending' : 'action_required')),
                ],
                'bib' => [
                    'required' => true,
                    'completed' => $bibAssigned,
                    'status' => $bibAssigned ? 'complete' : 'pending',
                ],
                'waiver' => [
                    'required' => true,
                    'completed' => $waiverComplete,
                    'status' => $waiverComplete ? 'complete' : 'action_required',
                ],
                'first_aid_kit' => [
                    'required' => true,
                    'completed' => $firstAidComplete,
                    'status' => $firstAidComplete ? 'complete' : 'action_required',
                ],
                'medical_certificate' => [
                    'required' => $medicalCertificateRequired,
                    'completed' => $medicalCertificateComplete,
                    'status' => ! $medicalCertificateRequired
                        ? 'not_required'
                        : ($medicalCertificateComplete ? 'complete' : 'action_required'),
                ],
                'check_in' => [
                    'required' => true,
                    'completed' => $isCheckedIn,
                    'status' => $isCheckedIn ? 'complete' : ($readyForEventDay ? 'upcoming' : 'blocked'),
                ],
                'race_kit' => [
                    'required' => true,
                    'completed' => $raceKitReleased,
                    'status' => $raceKitReleased ? 'complete' : ($isCheckedIn ? 'pending' : 'upcoming'),
                ],
                'result' => [
                    'required' => false,
                    'completed' => $result !== null,
                    'status' => $result ? 'available' : ($isCheckedIn ? 'pending' : 'upcoming'),
                ],
                'e_badge' => [
                    'required' => false,
                    'completed' => $issuedBadges->isNotEmpty(),
                    'status' => $issuedBadges->isNotEmpty() ? 'available' : 'not_available',
                ],
            ],
            'requirements' => [
                'qualification_notes' => $category?->qualification_notes,
                'gear_label' => $gearKey ? ($gearKey === 'required_gear' ? 'Required Gear' : 'Mandatory Gear') : null,
                'gear' => $gearKey ? $categoryDetails[$gearKey] : null,
            ],
            'schedule' => [
                'scheduled_start_date' => optional($category?->scheduled_start_date ?? $registration->event?->event_date)->format('Y-m-d'),
                'scheduled_start_time' => optional($category?->scheduled_start_time)->format('H:i'),
                'scheduled_end_date' => optional($category?->scheduled_end_date ?? $category?->scheduled_start_date ?? $registration->event?->event_date)->format('Y-m-d'),
                'scheduled_end_time' => optional($category?->scheduled_end_time)->format('H:i'),
                'scheduled_start_at' => optional($category?->scheduledStartAt())->toIso8601String(),
                'scheduled_end_at' => optional($category?->scheduledEndAt())->toIso8601String(),
            ],
            'result' => $result ? [
                'id' => $result->id,
                'finish_time' => $result->finish_time,
                'rank_overall' => $result->rank_overall,
                'rank_category' => $result->rank_category,
                'remarks' => $result->remarks,
            ] : null,
            'e_badges' => [
                'available' => $issuedBadges->isNotEmpty(),
                'count' => $issuedBadges->count(),
                'items' => $issuedBadges->map(fn ($issuedBadge) => [
                    'id' => $issuedBadge->id,
                    'badge_id' => $issuedBadge->e_badge_id,
                    'title' => $issuedBadge->badge?->title,
                    'image_url' => $issuedBadge->badge?->image_path
                        ? asset('storage/'.$issuedBadge->badge->image_path)
                        : null,
                    'issued_at' => optional($issuedBadge->issued_at)?->toISOString(),
                ])->values()->all(),
            ],
        ];
    }

    private function overallStatus(
        string $registrationStatus,
        bool $paymentWaiting,
        bool $participantRequirementsComplete,
        bool $bibAssigned,
        bool $hasResult
    ): string {
        if ($registrationStatus === 'rejected') {
            return 'rejected';
        }

        if ($registrationStatus === 'completed' || $hasResult) {
            return 'completed';
        }

        if ($registrationStatus === 'checked_in') {
            return 'checked_in';
        }

        if ($paymentWaiting) {
            return 'awaiting_payment_confirmation';
        }

        if (! $participantRequirementsComplete) {
            return 'action_required';
        }

        if ($registrationStatus === 'pending') {
            return 'awaiting_approval';
        }

        if (! $bibAssigned) {
            return 'awaiting_bib';
        }

        return 'ready_for_event_day';
    }

    private function nextAction(
        Registration $registration,
        bool $isRejected,
        bool $isApproved,
        bool $isCheckedIn,
        bool $paymentComplete,
        bool $paymentWaiting,
        bool $waiverComplete,
        bool $medicalCertificateComplete,
        bool $firstAidComplete,
        bool $bibAssigned,
        bool $raceKitReleased,
        bool $hasResult
    ): array {
        if ($isRejected) {
            return $this->action('registration_rejected', 'Registration rejected', $registration->rejection_reason, 'blocked');
        }

        if (! $paymentComplete) {
            return $paymentWaiting
                ? $this->action('await_payment_confirmation', 'Await payment confirmation', 'Your payment is being processed or reviewed.', 'waiting')
                : $this->action('complete_payment', 'Complete payment', 'Complete payment to continue your registration.', 'action_required');
        }

        if (! $waiverComplete) {
            return $this->action('complete_waiver', 'Complete waiver', 'Accept the participant waiver before event day.', 'action_required');
        }

        if (! $medicalCertificateComplete) {
            return $this->action('submit_medical_certificate', 'Submit medical certificate', 'This category requires a medical certificate.', 'action_required');
        }

        if (! $firstAidComplete) {
            return $this->action('confirm_first_aid_kit', 'Confirm first-aid kit', 'Confirm that you will bring the required first-aid kit.', 'action_required');
        }

        if (! $isApproved) {
            return $this->action('await_approval', 'Await registration approval', 'Your completed registration is waiting for administrator review.', 'waiting');
        }

        if (! $bibAssigned) {
            return $this->action('await_bib_assignment', 'Await bib assignment', 'Your registration is approved and your bib is being prepared.', 'waiting');
        }

        if (! $isCheckedIn) {
            return $this->action('event_day_check_in', 'Check in on event day', 'Present your approved registration and bib at event check-in.', 'upcoming');
        }

        if (! $raceKitReleased) {
            return $this->action('collect_race_kit', 'Collect race kit', 'Your check-in is complete. Collect your race kit from the organizer.', 'action_required');
        }

        if (! $hasResult) {
            return $this->action('await_results', 'Await race results', 'Your result will appear after it is recorded by the organizer.', 'waiting');
        }

        return $this->action('view_results', 'View race results', 'Your official result is available.', 'complete');
    }

    private function action(string $key, string $label, ?string $message, string $status): array
    {
        return compact('key', 'label', 'message', 'status');
    }
}
