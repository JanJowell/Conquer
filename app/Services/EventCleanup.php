<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventCleanup
{
    // Include raw/soft-deleted rows and non-cascading references as well as
    // participant history. Only an unused automatic draft announcement is exempt.
    private const DEPENDENT_TABLES = [
        'categories', 'registrations', 'registration_groups', 'payments',
        'race_results', 'finish_scans', 'registration_feedback', 'certificates',
        'issued_e_badges', 'e_badges', 'event_payment_methods', 'checkpoints',
        'community_posts', 'checkpoint_scan_plans', 'checkpoint_scans', 'event_reviews', 'event_auto_archive_logs',
    ];

    public function available(): bool
    {
        return Schema::hasColumns('events', ['archived_at', 'archived_by_user_id']);
    }

    public function actionFor(Event $event): ?string
    {
        if ($event->revision_of_id !== null) {
            return null;
        }
        if ($event->archived_at !== null) {
            return 'restore';
        }
        if ($this->canArchive($event)) {
            return 'archive';
        }

        return $this->canDelete($event) ? 'delete' : null;
    }

    public function fingerprint(Event $event): string
    {
        // Ignore list-only aggregate columns; use stored values rather than
        // time-derived status or formatted dates. Include non-fillable review
        // and archive metadata, without adding it to editable event setup.
        $values = array_intersect_key($event->getRawOriginal(), array_flip([
            'id', ...$event->getFillable(), 'created_at', 'updated_at',
            'review_status', 'review_version', 'review_reason', 'submitted_at',
            'approved_at', 'approved_by', 'revision_of_id', 'submitted_setup_hash',
            'archived_at', 'archived_by_user_id', 'auto_archive_exempt_at',
            'first_published_at', 'auto_archive_restored_at',
            'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
        ]));
        ksort($values);

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function perform(Event $event, User $actor, string $action, string $fingerprint, ?string $title = null, string $ip = 'unknown'): void
    {
        if (! $this->available()) {
            $this->reject('Event cleanup is unavailable until its database migration is applied through the normal release process.');
        }

        DB::transaction(function () use ($event, $actor, $action, $fingerprint, $title, $ip) {
            $staff = User::query()->whereKey($actor->id)->sharedLock()->first();
            abort_unless($staff?->isSuperAdmin() && $staff->email_verified_at
                && ! $staff->isSuspended() && ! $staff->isBanned()
                && (! $staff->two_factor_required || $staff->two_factor_confirmed_at), 403);
            // Setup/review writers lock the same parent. Child FK writers cannot
            // race a destructive dependency check while this lock is held.
            $current = Event::query()->lockForUpdate()->findOrFail($event->id);
            if (! hash_equals($this->fingerprint($current), $fingerprint)) {
                $this->reject('This event changed. Refresh the event list before confirming cleanup again.');
            }
            if ($this->actionFor($current) !== $action) {
                $this->reject('This event is not eligible for that cleanup action. Only empty, never-submitted drafts can be deleted. Only completed events without active revisions, or cancelled events, can be archived.');
            }
            if ($action === 'delete') {
                if ($title !== $current->title) {
                    $this->reject('Type the event title exactly to confirm permanent deletion.');
                }
                // Never orphan a draft announcement into a public global post.
                $current->announcements()->where('is_auto_generated', true)->where('is_published', false)->delete();
                $current->delete();
            } else {
                // Do not run event-saving lifecycle/announcement hooks: archiving
                // is not approval, unpublishing, cancellation or a setup edit.
                $metadata = [
                    'archived_at' => $action === 'archive' ? now() : null,
                    'archived_by_user_id' => $action === 'archive' ? $staff->id : null,
                    'updated_at' => now(),
                ];
                if ($action === 'restore' && Schema::hasColumn('events', 'auto_archive_exempt_at')) {
                    if (Schema::hasColumn('events', 'auto_archive_restored_at')) {
                        // A deliberate new restoration opts even an older event
                        // into a fresh window. Preserve old exemptions until then.
                        $metadata['auto_archive_restored_at'] = now();
                        $metadata['auto_archive_exempt_at'] = null;
                    } else {
                        // Keep manual cleanup usable during a rolling migration.
                        $metadata['auto_archive_exempt_at'] = now();
                    }
                }
                DB::table('events')->where('id', $current->id)->update($metadata);
            }
            // Record successful outcomes transactionally, separate from review
            // history. A failed audit write rolls the cleanup back as well.
            AdminActivityLog::create(['user_id' => $staff->id,
                'action' => 'event.cleanup.'.$action.' event='.$current->id, 'ip_address' => $ip]);
        }, 3);
    }

    private function canArchive(Event $event): bool
    {
        return $event->isReviewPublished() && ($event->isCancelled()
            || ($event->effective_status === 'completed' && ! $event->activeRevision()->exists()));
    }

    private function canDelete(Event $event): bool
    {
        if (! in_array($event->review_status, [null, 'draft'], true)
            || $event->getRawOriginal('status') !== 'draft' || $event->review_version !== 0
            || $event->submitted_at || $event->approved_at || $event->approved_by
            || $event->submitted_setup_hash || $event->revisions()->exists()
            || DB::table('event_revisions')->where('event_id', $event->id)->orWhere('draft_event_id', $event->id)->exists()) {
            return false;
        }
        foreach (self::DEPENDENT_TABLES as $table) {
            // The additive automation migration is separate from manual cleanup.
            if ($table === 'event_auto_archive_logs' && ! Schema::hasTable($table)) {
                continue;
            }
            if (DB::table($table)->where('event_id', $event->id)->exists()) {
                return false;
            }
        }

        return ! $event->announcements()->where(fn ($query) => $query
            ->where('is_auto_generated', false)->orWhere('is_published', true))->exists();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['cleanup' => $message]);
    }
}
