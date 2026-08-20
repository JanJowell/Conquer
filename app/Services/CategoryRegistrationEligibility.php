<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Registration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CategoryRegistrationEligibility
{
    public const SAFETY_BUFFER_MINUTES = 30;

    /**
     * @param  Collection<int, Registration>  $registrations
     * @return array{allowed: bool, reason: ?string, conflicting_registration: ?Registration}
     */
    public function evaluate(Category $candidate, Collection $registrations): array
    {
        $blockingRegistrations = $registrations->filter(
            fn (Registration $registration) => $registration->status !== 'rejected'
                && (int) $registration->category_id !== (int) $candidate->id
        );

        if ($blockingRegistrations->isEmpty()) {
            return [
                'allowed' => true,
                'reason' => null,
                'conflicting_registration' => null,
            ];
        }

        $candidateStart = $this->categoryStartAt($candidate);
        $candidateEnd = $this->categoryEndAt($candidate);

        if (! $candidateStart || ! $candidateEnd) {
            return [
                'allowed' => false,
                'reason' => 'This category does not have a complete gun start and cutoff/end schedule.',
                'conflicting_registration' => null,
            ];
        }

        foreach ($blockingRegistrations as $registration) {
            $registeredCategory = $registration->category;
            $registeredStart = $registeredCategory ? $this->categoryStartAt($registeredCategory) : null;
            $registeredEnd = $registeredCategory ? $this->categoryEndAt($registeredCategory) : null;

            if (! $registeredStart || ! $registeredEnd) {
                return [
                    'allowed' => false,
                    'reason' => 'An existing registration does not have a complete category schedule.',
                    'conflicting_registration' => $registration,
                ];
            }

            $overlapsWithBuffer = $candidateStart->lt($registeredEnd->copy()->addMinutes(self::SAFETY_BUFFER_MINUTES))
                && $registeredStart->lt($candidateEnd->copy()->addMinutes(self::SAFETY_BUFFER_MINUTES));

            if ($overlapsWithBuffer) {
                return [
                    'allowed' => false,
                    'reason' => "This category conflicts with {$registeredCategory->name}. A ".self::SAFETY_BUFFER_MINUTES.'-minute gap is required between categories.',
                    'conflicting_registration' => $registration,
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
            'conflicting_registration' => null,
        ];
    }

    private function categoryStartAt(Category $category): ?Carbon
    {
        return $category->scheduled_start_time ? $category->scheduledStartAt() : null;
    }

    private function categoryEndAt(Category $category): ?Carbon
    {
        return $category->scheduled_end_time ? $category->scheduledEndAt() : null;
    }
}
