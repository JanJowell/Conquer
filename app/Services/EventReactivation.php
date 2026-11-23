<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventReactivation
{
    public function available(): bool
    {
        return Schema::hasColumns('event_revisions', ['purpose', 'cancellation_snapshot']);
    }

    public function unused(Event $event, bool $locking = false): bool
    {
        foreach (['registrations', 'registration_groups', 'payments', 'finish_scans', 'checkpoint_scans',
            'checkpoint_scan_plans', 'race_results', 'certificates', 'issued_e_badges', 'registration_feedback'] as $table) {
            // Raw rows include historical/rejected records and any soft deletes.
            // At a transition use current locking reads, not an old RR snapshot.
            if (DB::table($table)->where('event_id', $event->id)->when($locking, fn ($query) => $query->sharedLock())->first(['id'])) {
                return false;
            }
        }

        return ! DB::table('categories')->where('event_id', $event->id)
            ->where(fn ($query) => $query->whereNotNull('started_at')->orWhereNotNull('started_by_user_id'))
            ->when($locking, fn ($query) => $query->sharedLock())->first(['id']);
    }

    public function eligible(Event $event): bool
    {
        return $this->available() && $event->isCancelled() && $event->cancelled_at && $event->isReviewPublished()
            && ! $event->archived_at && $this->unused($event);
    }

    public function canRequest(Event $event, User $user): bool
    {
        return $user->managesAssignedEventsOnly() && $user->canManageEvent($event) && $this->eligible($event);
    }

    public function snapshot(Event $event): array
    {
        return $event->only(['cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'review_version']);
    }

    public function assertEligible(Event $event): void
    {
        if (! $this->available() || ! $event->isCancelled() || ! $event->cancelled_at || ! $event->isReviewPublished() || $event->archived_at
            || ! $this->unused($event, true)) {
            throw ValidationException::withMessages(['reactivation' => 'Only an unarchived cancelled event with no registrations, payments, category starts or race history can be reactivated. Archived events must first be restored by Admin.']);
        }
    }

    public function matchesCancellation(Event $draft, Event $parent): bool
    {
        $stored = $draft->revisionRequest?->cancellation_snapshot;

        $expected = json_decode(json_encode($this->snapshot($parent), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        foreach ($expected as $key => $value) {
            if (! is_array($stored) || ! array_key_exists($key, $stored) || $stored[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
