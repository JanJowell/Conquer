<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventReviewWorkflow
{
    public function transition(Event $event, User $actor, string $action, int $version, ?string $reason = null): Event
    {
        return DB::transaction(function () use ($event, $actor, $action, $version, $reason) {
            $parentId = Event::whereKey($event->id)->value('revision_of_id');
            $parent = $parentId ? Event::query()->lockForUpdate()->findOrFail($parentId) : null;
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            if ($parent) {
                $event->setRelation('revisionOf', $parent);
            }
            $reactivation = $event->isReactivationDraft();
            if (in_array($action, ['submit', 'withdraw', 'discard'], true)) {
                abort_unless($actor->managesAssignedEventsOnly() && $actor->canManageEvent($event), 403);
            } else {
                abort_unless($actor->isSuperAdmin(), 403);
            }
            if ($reactivation && in_array($action, ['submit', 'approve'], true)) {
                $policy = app(EventReactivation::class);
                $policy->assertEligible($parent);
                abort_unless($policy->matchesCancellation($event, $parent), 409, 'The cancellation changed. Prepare a new reactivation request.');
            } elseif (! $reactivation) {
                abort_if(($parent ?? $event)->isCancelled(), 409, 'Use the explicit reactivation request for an eligible cancelled event.');
            }
            abort_unless($event->review_version === $version, 409, 'This submission changed. Refresh before reviewing it.');
            $from = $event->review_status;

            if ($action === 'submit') {
                abort_unless($event->canEditSetup($actor), 409, 'Only an editable draft can be submitted.');
                $this->assertReady($event);
                $event->forceFill(['review_status' => 'pending_review', 'submitted_at' => now(),
                    'review_reason' => null, 'submitted_setup_hash' => $this->setupHash($event)]);
            } elseif ($action === 'withdraw') {
                abort_unless($from === 'pending_review', 409, 'Only a pending submission can be withdrawn.');
                $event->forceFill(['review_status' => 'draft', 'submitted_at' => null, 'submitted_setup_hash' => null]);
            } elseif ($action === 'discard') {
                // Invalid/archived reactivation requests must still be dismissible;
                // this never changes or reopens the cancelled parent.
                abort_unless($event->revision_of_id && ($reactivation
                    ? in_array($from, ['draft', 'changes_requested'], true) : $event->canEditSetup($actor)), 409,
                    'Only a draft revision can be discarded.');
                $event->forceFill(['review_status' => 'discarded', 'submitted_setup_hash' => null]);
            } elseif ($action === 'return') {
                abort_unless($from === 'pending_review', 409, 'Only a pending submission can be returned.');
                if (blank(trim($reason ?? ''))) {
                    throw ValidationException::withMessages(['reason' => 'Explain what the manager needs to change.']);
                }
                $event->forceFill(['review_status' => 'changes_requested', 'review_reason' => trim($reason)]);
            } elseif ($action === 'approve') {
                abort_unless($from === 'pending_review', 409, 'Only a pending submission can be approved.');
                abort_unless(hash_equals($event->submitted_setup_hash ?? '', $this->setupHash($event)), 409,
                    'The submitted setup changed. Withdraw and resubmit it for review.');
                $this->assertReady($event);
                if ($event->revision_of_id) {
                    $published = $this->applyRevision($event, $actor);
                } elseif ($event->first_published_at === null && Schema::hasColumn('events', 'first_published_at')) {
                    // Original approval is the first publication. Revision approvals
                    // update approved_at separately and must never reset this clock.
                    $event->forceFill(['first_published_at' => now()]);
                }
                $event->forceFill(['review_status' => 'approved', 'approved_at' => now(),
                    'approved_by' => $actor->id, 'review_reason' => null]);
            } else {
                abort(404);
            }
            $event->review_version++;
            $event->save();
            $event->refreshAutomaticStatus();
            $this->audit($event, $actor, $action, $from, $reason);

            return $published ?? $event;
        });
    }

    public function startRevision(Event $event, User $actor, int $version, ?string $reactivationReason = null): Event
    {
        return DB::transaction(function () use ($event, $actor, $version, $reactivationReason) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($actor->managesAssignedEventsOnly() && $actor->canManageEvent($event), 403);
            abort_unless($event->review_version === $version, 409, 'Refresh the event before starting a revision.');
            $reactivation = $reactivationReason !== null;
            if ($reactivation) {
                app(EventReactivation::class)->assertEligible($event);
                if (blank(trim($reactivationReason)) || mb_strlen($reactivationReason) > 1000) {
                    throw ValidationException::withMessages(['reactivation' => 'Explain why this unused event should be reactivated (up to 1000 characters).']);
                }
            } else {
                abort_unless($event->isReviewPublished() && $event->effective_status === 'upcoming', 409,
                    'Reviewed revisions are available for published upcoming events only.');
            }
            $existing = Event::query()->where('revision_of_id', $event->id)
                ->whereIn('review_status', ['draft', 'changes_requested', 'pending_review'])
                ->when($reactivation, fn ($query) => $query->whereHas('revisionRequest', fn ($request) => $request->where('purpose', 'reactivation')))
                ->first();
            if ($existing) {
                return $existing;
            }
            $draft = $event->replicate();
            if (Schema::hasColumns('events', ['first_published_at', 'auto_archive_restored_at'])) {
                $draft->forceFill(['first_published_at' => null, 'auto_archive_restored_at' => null]);
            }
            $draft->forceFill(['slug' => 'revision-'.Str::uuid(), 'review_status' => 'draft', 'status' => 'draft',
                'cancelled_at' => null, 'cancelled_by_user_id' => null, 'cancellation_reason' => null,
                'archived_at' => null, 'archived_by_user_id' => null, 'auto_archive_exempt_at' => null,
                'revision_of_id' => $event->id, 'review_version' => 0, 'submitted_at' => null,
                'approved_at' => null, 'approved_by' => null, 'review_reason' => null, 'submitted_setup_hash' => null]);
            $draft->save();
            $maps = [];
            foreach (['categories', 'paymentMethods', 'checkpoints'] as $relation) {
                $maps[$relation] = [];
                foreach ($event->{$relation}()->orderBy('id')->get() as $item) {
                    $copy = $item->replicate();
                    $copy->event_id = $draft->id;
                    if ($copy instanceof Category) {
                        $copy->started_at = null;
                        $copy->started_by_user_id = null;
                    }
                    $copy->save();
                    $maps[$relation][(string) $item->id] = $copy->id;
                }
            }
            $metadata = $reactivation ? ['purpose' => 'reactivation', 'cancellation_snapshot' => [
                ...app(EventReactivation::class)->snapshot($event), 'request_reason' => trim($reactivationReason),
            ]] : [];
            EventRevision::create([...$metadata, 'event_id' => $event->id, 'draft_event_id' => $draft->id,
                'base_setup_hash' => $this->setupHash($event), 'category_map' => $maps['categories'],
                'payment_method_map' => $maps['paymentMethods'], 'checkpoint_map' => $maps['checkpoints']]);
            $this->audit($draft, $actor, $reactivation ? 'reactivation_requested' : 'revision_created', null, $reactivationReason);

            return $draft;
        });
    }

    public function setupHash(Event $event): string
    {
        return hash('sha256', json_encode($this->snapshot($event), JSON_THROW_ON_ERROR));
    }

    public function snapshot(Event $event): array
    {
        $event = $event->fresh();
        $snapshot = ['event' => $this->rawSetup($event)];
        foreach (['categories', 'paymentMethods', 'checkpoints'] as $relation) {
            $snapshot[$relation] = $event->{$relation}()->reorder('id')->get()
                ->mapWithKeys(fn (Model $item) => [(string) $item->id => $this->rawSetup($item)])->all();
        }

        return $snapshot;
    }

    private function fields(Model $item): array
    {
        return array_values(array_diff($item->getFillable(), ['event_id', 'status', 'created_at', 'updated_at',
            'slug', 'organized_by', 'started_at', 'started_by_user_id']));
    }

    private function rawSetup(Model $item): array
    {
        // Category registration availability is part of reviewed setup, unlike event lifecycle.
        $fields = $this->fields($item);
        if ($item instanceof Category) {
            $fields[] = 'status';
        }

        return array_intersect_key($item->getAttributes(), array_flip($fields));
    }

    private function copySetup(Model $from, Model $to): void
    {
        foreach (array_keys($this->rawSetup($from)) as $field) {
            $to->setAttribute($field, $from->getAttribute($field));
        }
        $to->save();
    }

    private function assertReady(Event $event): void
    {
        $errors = [...$event->publicReadinessErrors(), ...app(EventSubmissionRequirements::class)->errors($event)];
        if ($event->event_date?->isBefore(today()) || $event->registration_deadline?->isBefore(today())
            || Event::statusForDate('upcoming', $event->event_date, $event->start_time, $event->end_time, $event->event_end_date) !== 'upcoming') {
            $errors[] = 'use an upcoming event and an unexpired registration deadline';
        }
        $manager = $event->manager;
        if (! $manager || ! $manager->managesAssignedEventsOnly() || $manager->isSuspended() || $manager->isBanned()
            || ! $manager->email_verified_at) {
            $errors[] = 'assign an active, verified Event Manager';
        }
        $conflict = app(EventManagerAvailability::class)->conflictingEvent((int) $event->manager_id,
            $event->getAttributes(), $event->revision_of_id ?: $event->id);
        if ($conflict) {
            $errors[] = 'resolve the Event Manager schedule conflict';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['review' => 'Before submission/approval: '.implode('; ', $errors).'.']);
        }
    }

    private function applyRevision(Event $draft, User $actor): Event
    {
        $revision = EventRevision::query()->where('draft_event_id', $draft->id)->lockForUpdate()->firstOrFail();
        $live = Event::query()->lockForUpdate()->findOrFail($revision->event_id);
        $reactivation = $revision->purpose === 'reactivation';
        if ($reactivation) {
            app(EventReactivation::class)->assertEligible($live);
            abort_unless(app(EventReactivation::class)->matchesCancellation($draft, $live), 409, 'The cancellation changed. Prepare a new reactivation request.');
        }
        abort_unless(! $revision->applied_at && $live->isReviewPublished() && ($reactivation || $live->effective_status === 'upcoming'), 409,
            'The published event can no longer accept this revision.');
        abort_unless(hash_equals($revision->base_setup_hash, $this->setupHash($live)), 409,
            'The published setup changed since this revision began. Start a fresh revision.');
        // Lock category rows also used by registration, then inspect current dependencies.
        $live->categories()->reorder('id')->lockForUpdate()->get();
        if ($live->registrations()->exists() && $live->interest_type !== $draft->interest_type) {
            throw ValidationException::withMessages(['review' => 'The event type cannot change after participants register.']);
        }
        foreach (['categories' => $revision->category_map, 'paymentMethods' => $revision->payment_method_map,
            'checkpoints' => $revision->checkpoint_map] as $relation => $map) {
            $items = $draft->{$relation}()->reorder('id')->get();
            $used = [];
            foreach ($live->{$relation}()->reorder('id')->get() as $old) {
                $copy = $items->firstWhere('id', $map[(string) $old->id] ?? null);
                if (! $copy && $relation === 'paymentMethods') {
                    // The existing form recreates draft payment rows on save.
                    // Match unchanged providers without replacing public IDs.
                    $copy = $items->firstWhere('provider', $old->provider);
                }
                if ($old instanceof Category) {
                    $this->assertCategorySafe($old, $copy);
                }
                if (! $copy) {
                    if ($old instanceof Category) {
                        // Keep category IDs and any historical setup references.
                        $old->update(['status' => 'closed']);
                    } elseif ($relation === 'paymentMethods') {
                        // Existing payment payloads may retain this method ID.
                        $old->update(['is_enabled' => false]);
                    } else {
                        $old->delete();
                    }
                } else {
                    $used[] = $copy->id;
                    $this->copySetup($copy, $old);
                }
            }
            foreach ($items as $item) {
                if (! in_array($item->id, $used, true)) {
                    $copy = $item->replicate();
                    $copy->event_id = $live->id;
                    $copy->save();
                }
            }
        }
        $from = $live->review_status;
        $this->copySetup($draft, $live);
        $approval = ['review_status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id,
            'review_reason' => null, 'review_version' => $live->review_version + 1];
        if ($reactivation) {
            // The cancellation remains effective while all setup is copied.
            // Clear only its active flags atomically after every approval check;
            // the immutable revision snapshot retains the full cancellation.
            DB::table('events')->where('id', $live->id)->update([...$approval, 'status' => 'upcoming',
                'cancelled_at' => null, 'cancelled_by_user_id' => null, 'cancellation_reason' => null, 'updated_at' => now()]);
            $live->refresh();
        } else {
            $live->forceFill($approval)->save();
        }
        $live->refreshAutomaticStatus();
        $revision->update(['applied_at' => now()]);
        $this->audit($live, $actor, $reactivation ? 'reactivation_approved' : 'revision_published', $from,
            $reactivation ? $revision->cancellation_snapshot['request_reason'] : null);

        return $live;
    }

    private function assertCategorySafe(Category $live, ?Category $draft): void
    {
        $inUse = $live->registrations()->exists() || $live->raceResults()->exists();
        if (! $draft && ($inUse || $live->started_at)) {
            throw ValidationException::withMessages(['review' => 'A category with registrations, results or a start time cannot be removed.']);
        }
        if (! $draft) {
            return;
        }
        $locked = $inUse ? ['name', 'distance_km', 'distance_unit', 'requires_medical_certificate',
            'participation_mode', 'group_min_members', 'group_max_members'] : [];
        if ($live->started_at) {
            $locked = [...$locked, 'scheduled_start_date', 'scheduled_start_time', 'scheduled_end_date', 'scheduled_end_time'];
        }
        foreach ($locked as $field) {
            if ($live->getRawOriginal($field) !== $draft->getRawOriginal($field)) {
                throw ValidationException::withMessages(['review' => 'Registered or started category identity/eligibility/timing cannot change.']);
            }
        }
        if ($inUse) {
            foreach (config("conquer.event_category_type_details.{$live->event->interest_type}", []) as $key => $definition) {
                if (($definition['locked_when_in_use'] ?? false) && ($live->type_details[$key] ?? null) !== ($draft->type_details[$key] ?? null)) {
                    throw ValidationException::withMessages(['review' => 'Registered category requirements cannot change.']);
                }
            }
        }
        if ($draft->slot_limit !== null && $draft->slot_limit < $live->registrations()->where('status', '!=', 'rejected')->count()) {
            throw ValidationException::withMessages(['review' => 'Slots cannot be reduced below current registrations.']);
        }
    }

    private function audit(Event $event, User $actor, string $action, ?string $from, ?string $reason = null): void
    {
        $snapshot = null;
        if ($action === 'submit') {
            $revision = $event->revision_of_id ? EventRevision::where('draft_event_id', $event->id)->firstOrFail() : null;
            $snapshot = ['setup' => $this->snapshot($event),
                'published_setup' => $revision ? $this->snapshot(Event::findOrFail($revision->event_id)) : null,
                'maps' => $revision ? ['categories' => $revision->category_map, 'paymentMethods' => $revision->payment_method_map,
                    'checkpoints' => $revision->checkpoint_map] : []];
            if ($revision?->purpose === 'reactivation') {
                $snapshot['reactivation'] = $revision->cancellation_snapshot;
            }
        }
        $event->reviews()->create(['actor_id' => $actor->id, 'action' => $action, 'from_status' => $from,
            'to_status' => $event->review_status, 'review_version' => $event->review_version,
            'reason' => $reason, 'setup_hash' => $this->setupHash($event), 'submission_snapshot' => $snapshot]);
    }
}
