<?php

namespace App\Services;

use App\Models\EBadge;
use App\Models\IssuedEBadge;
use App\Models\Registration;

class EBadgeAutoIssuer
{
    public const MANUAL = 'manual';
    public const COMPLETED_EVENT = 'completed_event';
    public const FIRST_OVERALL = 'first_overall';
    public const SECOND_OVERALL = 'second_overall';
    public const THIRD_OVERALL = 'third_overall';
    public const TOP_3_OVERALL = 'top_3_overall';
    public const FIRST_CATEGORY = 'first_category';
    public const SECOND_CATEGORY = 'second_category';
    public const THIRD_CATEGORY = 'third_category';
    public const TOP_3_CATEGORY = 'top_3_category';

    public function __construct(private readonly EBadgeNotificationService $notifications)
    {
    }

    public static function rules(): array
    {
        return [
            self::MANUAL => 'Manual only',
            self::COMPLETED_EVENT => 'Completed event / Finisher',
            self::FIRST_OVERALL => '1st place overall',
            self::SECOND_OVERALL => '2nd place overall',
            self::THIRD_OVERALL => '3rd place overall',
            self::TOP_3_OVERALL => 'Top 3 overall',
            self::FIRST_CATEGORY => '1st place in category',
            self::SECOND_CATEGORY => '2nd place in category',
            self::THIRD_CATEGORY => '3rd place in category',
            self::TOP_3_CATEGORY => 'Top 3 in category',
        ];
    }

    public function issueForCompletedRegistration(Registration $registration): int
    {
        $registration->loadMissing(['raceResult', 'event', 'user']);

        if ($registration->status !== 'completed' || ! $registration->raceResult) {
            return 0;
        }

        $badges = EBadge::query()
            ->where('is_active', true)
            ->where('auto_issue_rule', '!=', self::MANUAL)
            ->where(function ($query) use ($registration) {
                $query->whereNull('event_id')
                    ->orWhere('event_id', $registration->event_id);
            })
            ->where(function ($query) use ($registration) {
                $query->whereNull('category_id')
                    ->orWhere('category_id', $registration->category_id);
            })
            ->get();

        $issued = 0;

        foreach ($badges as $badge) {
            if (! $this->registrationMatchesRule($registration, $badge)) {
                continue;
            }

            $created = IssuedEBadge::firstOrCreate(
                [
                    'e_badge_id' => $badge->id,
                    'registration_id' => $registration->id,
                ],
                [
                    'user_id' => $registration->user_id,
                    'event_id' => $registration->event_id,
                    'issued_by' => null,
                    'issued_at' => now(),
                    'notes' => 'Automatically issued',
                ]
            );

            if ($created->wasRecentlyCreated) {
                $this->notifications->notifyIssued($created);
                $issued++;
            }
        }

        return $issued;
    }

    public function syncForCompletedRegistrationsInEvent(int $eventId): array
    {
        $registrations = Registration::query()
            ->with(['raceResult', 'event', 'user'])
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->whereHas('raceResult')
            ->get();

        $issued = $registrations->sum(fn (Registration $registration) => $this->issueForCompletedRegistration($registration));
        $revoked = $this->revokeStaleAutoIssuedBadgesForEvent($eventId);

        return [
            'issued' => $issued,
            'revoked' => $revoked,
        ];
    }

    public function syncForBadge(EBadge $badge): array
    {
        $issued = $this->issueBadgeForCompletedRegistrations($badge);
        $revoked = $this->revokeStaleAutoIssuedBadgesForBadge($badge);

        return [
            'issued' => $issued,
            'revoked' => $revoked,
        ];
    }

    public function issueBadgeForCompletedRegistrations(EBadge $badge): int
    {
        if (! $badge->is_active || $badge->auto_issue_rule === self::MANUAL) {
            return 0;
        }

        return Registration::query()
            ->with(['raceResult', 'event', 'user'])
            ->where('status', 'completed')
            ->whereHas('raceResult')
            ->when($badge->event_id, fn ($query) => $query->where('event_id', $badge->event_id))
            ->when($badge->category_id, fn ($query) => $query->where('category_id', $badge->category_id))
            ->get()
            ->sum(function (Registration $registration) use ($badge) {
                if (! $this->registrationMatchesRule($registration, $badge)) {
                    return 0;
                }

                $created = IssuedEBadge::firstOrCreate(
                    [
                        'e_badge_id' => $badge->id,
                        'registration_id' => $registration->id,
                    ],
                    [
                        'user_id' => $registration->user_id,
                        'event_id' => $registration->event_id,
                        'issued_by' => null,
                        'issued_at' => now(),
                        'notes' => 'Automatically issued',
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $this->notifications->notifyIssued($created);

                    return 1;
                }

                return 0;
            });
    }

    private function registrationMatchesRule(Registration $registration, EBadge $badge): bool
    {
        $result = $registration->raceResult;
        $rule = $badge->auto_issue_rule;

        if ($badge->event_id !== null && (int) $badge->event_id !== (int) $registration->event_id) {
            return false;
        }

        if ($badge->category_id !== null && (int) $badge->category_id !== (int) $registration->category_id) {
            return false;
        }

        return match ($rule) {
            self::COMPLETED_EVENT => true,
            self::FIRST_OVERALL => $result->rank_overall === 1,
            self::SECOND_OVERALL => $result->rank_overall === 2,
            self::THIRD_OVERALL => $result->rank_overall === 3,
            self::TOP_3_OVERALL => $result->rank_overall !== null && $result->rank_overall <= 3,
            self::FIRST_CATEGORY => $result->rank_category === 1,
            self::SECOND_CATEGORY => $result->rank_category === 2,
            self::THIRD_CATEGORY => $result->rank_category === 3,
            self::TOP_3_CATEGORY => $result->rank_category !== null && $result->rank_category <= 3,
            default => false,
        };
    }

    private function revokeStaleAutoIssuedBadgesForEvent(int $eventId): int
    {
        $staleIssuedBadges = IssuedEBadge::query()
            ->with(['badge', 'registration.raceResult'])
            ->where('event_id', $eventId)
            ->whereNull('issued_by')
            ->where('notes', 'Automatically issued')
            ->whereHas('badge', function ($query) {
                $query->where('auto_issue_rule', '!=', self::MANUAL);
            })
            ->get()
            ->filter(function (IssuedEBadge $issuedBadge) {
                return ! $issuedBadge->badge
                    || ! $issuedBadge->registration
                    || $issuedBadge->registration->status !== 'completed'
                    || ! $this->registrationMatchesRule($issuedBadge->registration, $issuedBadge->badge);
            });

        $count = $staleIssuedBadges->count();

        $staleIssuedBadges->each->delete();

        return $count;
    }

    private function revokeStaleAutoIssuedBadgesForBadge(EBadge $badge): int
    {
        $staleIssuedBadges = IssuedEBadge::query()
            ->with(['badge', 'registration.raceResult'])
            ->where('e_badge_id', $badge->id)
            ->whereNull('issued_by')
            ->where('notes', 'Automatically issued')
            ->get()
            ->filter(function (IssuedEBadge $issuedBadge) {
                return ! $issuedBadge->badge
                    || $issuedBadge->badge->auto_issue_rule === self::MANUAL
                    || ! $issuedBadge->badge->is_active
                    || ! $issuedBadge->registration
                    || $issuedBadge->registration->status !== 'completed'
                    || ! $this->registrationMatchesRule($issuedBadge->registration, $issuedBadge->badge);
            });

        $count = $staleIssuedBadges->count();

        $staleIssuedBadges->each->delete();

        return $count;
    }
}
