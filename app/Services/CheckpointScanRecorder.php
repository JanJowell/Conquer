<?php

namespace App\Services;

use App\Exceptions\CheckpointScanException;
use App\Models\Category;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Rules\CategoryCourseMap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckpointScanRecorder
{
    public function __construct(private readonly FinishScanToken $tokens) {}

    public function plan(User $actor, int $eventId, int $categoryId): array
    {
        return DB::transaction(function () use ($actor, $eventId, $categoryId) {
            [, $event, $category] = $this->context($actor, $eventId, $categoryId);
            $plan = $this->currentPlan($event, $category);

            return ['event_id' => $event->id, 'category_id' => $category->id,
                'plan_fingerprint' => $plan['fingerprint'], 'category_started' => $category->started_at !== null && $category->started_at->lte(now()),
                'recording_mode' => 'online', 'checkpoints' => array_map(fn ($point) => [
                    'id' => $point['id'], 'position' => $point['position'], 'label' => $point['label'],
                ], $plan['snapshot']['checkpoints'])];
        }, 3);
    }

    /** @return array{scan: CheckpointScan, replayed: bool} */
    public function record(User $actor, array $input): array
    {
        $attempt = fn () => $this->recordLocked($actor, $input);
        try {
            return DB::transaction($attempt, 3);
        } catch (UniqueConstraintViolationException) {
            // A competing committed insert is re-read inside a fresh transaction.
            // No rollback/retry can leave an orphan plan or overwrite a visit.
            return DB::transaction($attempt, 3);
        }
    }

    private function recordLocked(User $actor, array $input): array
    {
        [$staff, $event, $category] = $this->context($actor, (int) $input['event_id'], (int) $input['category_id']);
        $resolved = $this->tokens->resolve($input['token']);
        if (! $resolved) {
            $this->fail('The BIB QR code is invalid or has been altered.', 'invalid_scan_token', 422);
        }
        $registration = Registration::query()->whereKey($resolved->id)
            ->where('event_id', $resolved->event_id)->where('category_id', $resolved->category_id)
            ->lockForUpdate()->first();
        if (! $registration) {
            $this->fail('The BIB registration changed. Refresh its current QR code.', 'invalid_scan_token', 422);
        }
        if ((int) $registration->event_id !== $event->id || (int) $registration->category_id !== $category->id) {
            $this->fail('This BIB belongs to a different event or category.', 'selection_mismatch', 422);
        }
        $clientId = strtolower($input['client_scan_id']);
        $requestFingerprint = hash('sha256', json_encode([$staff->id, $registration->id,
            $event->id, $category->id, (int) $input['checkpoint_id'], $input['plan_fingerprint']], JSON_THROW_ON_ERROR));
        $existing = CheckpointScan::query()->where('client_scan_id', $clientId)->lockForUpdate()->first();
        if ($existing) {
            if (! hash_equals($existing->request_fingerprint, $requestFingerprint)) {
                $this->fail('This scan identifier was already used for a different request.', 'client_scan_conflict');
            }

            // An authorized exact retry acknowledges history, even after a finish
            // or a later route edit. It never revalidates history as a new visit.
            return ['scan' => $existing, 'replayed' => true];
        }
        $plan = $this->currentPlan($event, $category);
        if (! hash_equals($plan['fingerprint'], $input['plan_fingerprint'])) {
            $this->fail('The checkpoint selection is stale. Refresh the current plan.', 'stale_checkpoint_plan');
        }
        $point = collect($plan['snapshot']['checkpoints'])->firstWhere('id', (int) $input['checkpoint_id']);
        if (! $point) {
            $this->fail('Select an active checkpoint belonging to this category.', 'checkpoint_not_available');
        }
        if (CheckpointScan::query()->where('registration_id', $registration->id)->where('checkpoint_id', $point['id'])->exists()) {
            $this->fail('This participant already has a recorded visit at this checkpoint.', 'checkpoint_already_recorded');
        }
        // Current locking reads also see a finish committed while the signed
        // registration lookup was waiting, rather than an older MySQL snapshot.
        if ($registration->status === 'completed' || $registration->raceResult()->lockForUpdate()->first()
            || $registration->finishScan()->lockForUpdate()->first()) {
            $this->fail('A finish or official result already exists. A checkpoint visit cannot be added retrospectively.', 'participant_already_finished');
        }
        if ($registration->status !== 'checked_in') {
            $this->fail('Only checked-in participants can receive a checkpoint scan.', 'participant_not_checked_in');
        }
        if (blank($registration->bib_number)) {
            $this->fail('This registration does not have an assigned BIB number.', 'bib_not_assigned');
        }
        $serverNow = now();
        if (! $category->started_at || $category->started_at->gt($serverNow)) {
            $this->fail('Start this category before recording checkpoint visits.', 'category_not_started');
        }
        $frozen = $plan['frozen'] ?? CheckpointScanPlan::create([
            'event_id' => $event->id, 'category_id' => $category->id, 'fingerprint' => $plan['fingerprint'],
            'category_started_at' => $category->started_at, 'snapshot' => $plan['snapshot'],
        ]);
        $cutoff = $category->scheduledEndAt();
        $scan = CheckpointScan::create([
            'checkpoint_scan_plan_id' => $frozen->id, 'event_id' => $event->id, 'category_id' => $category->id,
            'registration_id' => $registration->id, 'checkpoint_id' => $point['id'],
            'scanned_by_user_id' => $staff->id, 'scanned_by_name' => $staff->name,
            'client_scan_id' => $clientId, 'request_fingerprint' => $requestFingerprint,
            'participant_name' => $registration->user->name, 'bib_number' => $registration->bib_number,
            'checkpoint_label' => $point['label'], 'checkpoint_position' => $point['position'],
            'scanned_at' => $serverNow, 'elapsed_seconds' => (int) $category->started_at->diffInSeconds($serverNow),
            'after_cutoff' => $cutoff ? $serverNow->gt($cutoff) : false,
        ]);
        $scan->setRelation('plan', $frozen);

        return ['scan' => $scan, 'replayed' => false];
    }

    /** Protect authorization/setup reads without blocking finish-scan FK reads. */
    private function context(User $actor, int $eventId, int $categoryId): array
    {
        // Shared locks still prevent revocation/setup edits during acceptance.
        // Exclusive parent locks here can deadlock an existing finish writer:
        // it owns the registration and needs shared parent FK locks, while this
        // transaction waits for that registration. Keep the finish path intact.
        $staff = User::query()->whereKey($actor->id)->sharedLock()->first();
        if (! $staff || blank($staff->api_token) || ! hash_equals((string) $actor->api_token, $staff->api_token)
            || ($staff->api_token_expires_at && $staff->api_token_expires_at->lte(now()))) {
            $this->fail('The staff login changed or expired. Sign in again.', 'staff_session_changed', 401);
        }
        if ($staff->isSuspended()) {
            $this->fail('This account is currently suspended.', 'staff_suspended', 423);
        }
        if ($staff->isBanned() || ! $staff->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]) || ! $staff->email_verified_at) {
            $this->fail('This account is not authorized to record checkpoint visits.', 'checkpoint_scanner_forbidden', 403);
        }
        $event = Event::query()->whereKey($eventId)->sharedLock()->first();
        if (! $event || ($staff->managesAssignedEventsOnly() && (int) $event->manager_id !== $staff->id)) {
            $this->fail('You are not authorized to scan this event.', 'forbidden_event', 403);
        }
        if ($event->review_status !== 'approved' || $event->revision_of_id !== null || ! in_array($event->getRawOriginal('status'), ['upcoming', 'ongoing', 'completed'], true)) {
            $this->fail('Only an explicitly approved live event can record checkpoint visits.', 'event_not_approved');
        }
        $category = Category::query()->whereKey($categoryId)->where('event_id', $event->id)->sharedLock()->first();
        if (! $category || ! in_array($category->status, ['open', 'closed'], true)) {
            $this->fail('Select an available category belonging to this event.', 'category_not_available');
        }
        $category->setRelation('event', $event);

        return [$staff, $event, $category];
    }

    private function currentPlan(Event $event, Category $category): array
    {
        $source = $category->getRawOriginal('course_map');
        $valid = true;
        (new CategoryCourseMap)->validate('course_map', $source, function () use (&$valid): void {
            $valid = false;
        });
        $map = $valid ? CategoryCourseMap::normalize($source) : null;
        if (! $map || $map['checkpoints'] === []) {
            $this->fail('A valid saved route with named intermediate checkpoints is required.', 'checkpoint_list_not_ready');
        }
        $records = $category->activeCourseCheckpoints()->lockForUpdate()->get();
        if ($records->count() !== count($map['checkpoints'])) {
            $this->fail('Checkpoint identities need a reviewed synchronization before scanning.', 'checkpoint_list_not_ready');
        }
        $points = [];
        foreach ($records as $index => $row) {
            $point = $map['checkpoints'][$index];
            if ($row->position !== $index + 1 || $row->label !== $point['label']
                || round((float) $row->latitude, 6) !== $point['lat'] || round((float) $row->longitude, 6) !== $point['lng']) {
                $this->fail('Checkpoint identities do not match the current route.', 'checkpoint_list_not_ready');
            }
            $points[] = ['id' => $row->id, 'position' => $row->position, ...$point];
        }
        $snapshot = ['version' => 1, 'event_id' => $event->id, 'category_id' => $category->id,
            'event_title' => $event->title, 'category_name' => $category->name, 'review_version' => $event->review_version,
            'category_started_at' => $category->started_at?->toIso8601String(),
            'scheduled_end_at' => $category->scheduledEndAt()?->toIso8601String(), 'route' => $map, 'checkpoints' => $points];
        $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $frozen = CheckpointScanPlan::query()->where('category_id', $category->id)->lockForUpdate()->first();
        if ($frozen && ! hash_equals($frozen->fingerprint, $fingerprint)) {
            $this->fail('This category already has visits under a different plan. Stop scanning and request an administrator review.', 'checkpoint_plan_changed');
        }

        return ['snapshot' => $snapshot, 'fingerprint' => $fingerprint, 'frozen' => $frozen];
    }

    private function fail(string $message, string $code, int $status = 409): never
    {
        throw new CheckpointScanException($message, $code, $status);
    }
}
