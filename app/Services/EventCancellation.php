<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventCancellation
{
    public function available(): bool
    {
        return Schema::hasColumns('events', ['cancelled_at', 'cancelled_by_user_id', 'cancellation_reason']);
    }

    public function canCancel(Event $event): bool
    {
        return ! $event->isCancelled() && ! $event->archived_at && ! $event->revision_of_id
            && $event->isReviewPublished() && in_array($event->effective_status, ['upcoming', 'ongoing'], true);
    }

    public function cancel(Event $event, User $actor, string $fingerprint, string $reason, string $ip): void
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['cancellation' => 'Event cancellation requires its database migration.']);
        }
        DB::transaction(function () use ($event, $actor, $fingerprint, $reason, $ip) {
            $admin = User::query()->whereKey($actor->id)->sharedLock()->first();
            abort_unless($admin?->isSuperAdmin() && $admin->email_verified_at && ! $admin->isSuspended()
                && ! $admin->isBanned() && (! $admin->two_factor_required || $admin->two_factor_confirmed_at), 403);
            // New operational writers hold a shared event lock before child
            // locks. Cancellation changes only this parent and its announcement;
            // it never rewrites registrations, financial or race history.
            $current = Event::query()->lockForUpdate()->findOrFail($event->id);
            if (! hash_equals(app(EventCleanup::class)->fingerprint($current), $fingerprint)) {
                throw ValidationException::withMessages(['cancellation' => 'This event changed. Refresh before confirming cancellation.']);
            }
            if (! $this->canCancel($current) || blank(trim($reason)) || mb_strlen($reason) > 1000) {
                throw ValidationException::withMessages(['cancellation' => 'Only published upcoming or ongoing events can be cancelled. Provide a reason.']);
            }
            DB::table('events')->where('id', $current->id)->update(['status' => 'cancelled', 'cancelled_at' => now(),
                'cancelled_by_user_id' => $admin->id, 'cancellation_reason' => trim($reason), 'updated_at' => now()]);
            $current->announcements()->where('is_auto_generated', true)->update(['is_published' => false, 'updated_at' => now()]);
            AdminActivityLog::create(['user_id' => $admin->id, 'action' => 'event.cancel event='.$current->id, 'ip_address' => $ip]);
        }, 3);
    }
}
