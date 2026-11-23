<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAutoArchiveLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AutomaticEventArchiver
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly EventCleanup $cleanup) {}

    public function available(): bool
    {
        return $this->cleanup->available()
            && Schema::hasColumns('events', ['auto_archive_exempt_at', 'first_published_at', 'auto_archive_restored_at'])
            && Schema::hasColumns('event_auto_archive_logs', ['event_id', 'event_title', 'event_ended_at', 'cutoff_at', 'archived_at']);
    }

    public function activationTime(): ?CarbonImmutable
    {
        $value = config('event_archiving.activated_at');
        // Reject relative, ambiguous or invalid dates rather than broadening
        // the publication cohort. An explicit offset also survives TZ changes.
        if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
            return null;
        }
        $value = str_ends_with($value, 'Z') ? substr($value, 0, -1).'+00:00' : $value;
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);

            return $date && $date->format('Y-m-d\TH:i:sP') === $value
                ? $date->setTimezone(config('app.timezone')) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function run(bool $dryRun = false): int
    {
        if (! $dryRun && config('event_archiving.enabled') !== true) {
            return 0;
        }
        if (! $this->available()) {
            throw new RuntimeException('Automatic event archiving requires its database migration.');
        }
        $activation = $this->activationTime();
        if (! $activation) {
            throw new RuntimeException('Automatic event archiving requires a fixed activation timestamp with an explicit timezone offset.');
        }
        // Capture one app-timezone cutoff for the entire run, not one per row.
        $now = CarbonImmutable::now(config('app.timezone'));
        if ($now->lessThan($activation)) {
            return 0;
        }
        $cutoff = $now->subDays(self::RETENTION_DAYS);
        $count = 0;
        Event::query()->select('id')->whereNull('revision_of_id')
            ->whereNull('archived_at')
            ->where(function ($query) use ($activation, $cutoff) {
                $query->where(fn ($cancelled) => $cancelled->where('status', 'cancelled')
                    ->whereNotNull('cancelled_at')->where('cancelled_at', '<=', $cutoff->format('Y-m-d H:i:s')))
                    ->orWhere(fn ($completed) => $completed->where('status', '!=', 'cancelled')->whereNull('auto_archive_exempt_at')
                        ->where(fn ($cohort) => $cohort->where('first_published_at', '>=', $activation->format('Y-m-d H:i:s'))
                            ->orWhereNotNull('auto_archive_restored_at'))
                        ->whereDate(DB::raw('COALESCE(event_end_date, event_date)'), '<=', $cutoff->toDateString()));
            })
            ->where(fn ($query) => $query->whereNull('auto_archive_restored_at')
                ->orWhere('auto_archive_restored_at', '<=', $cutoff->format('Y-m-d H:i:s')))
            ->where(fn ($query) => $query->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->chunkById(100, function ($events) use ($activation, $cutoff, $dryRun, &$count) {
                foreach ($events as $candidate) {
                    $count += (int) DB::transaction(function () use ($candidate, $activation, $cutoff, $dryRun): bool {
                        // Same parent-row lock as Admin cleanup/setup/review writers.
                        // Re-read eligibility after the lock; the candidate list can be stale.
                        // Preview must also work in a database-enforced READ ONLY
                        // transaction; only the write path acquires a row lock.
                        $event = Event::query()->when(! $dryRun, fn ($query) => $query->lockForUpdate())->find($candidate->id);
                        if (! $event || $event->archived_at
                            || (! $event->isCancelled() && ($event->auto_archive_exempt_at
                                || (! $event->auto_archive_restored_at && (! $event->first_published_at || $event->first_published_at->lt($activation)))))
                            || $event->auto_archive_restored_at?->gt($cutoff)
                            || $this->cleanup->actionFor($event) !== 'archive') {
                            return false;
                        }
                        $endedAt = $this->endedAt($event);
                        if (! $endedAt || $endedAt->greaterThan($cutoff)) {
                            return false;
                        }
                        if (! $dryRun) {
                            $archivedAt = CarbonImmutable::now(config('app.timezone'));
                            // Bypass lifecycle/announcement hooks; retain all participant data.
                            DB::table('events')->where('id', $event->id)->update([
                                'archived_at' => $archivedAt, 'archived_by_user_id' => null,
                                'updated_at' => $archivedAt,
                            ]);
                            EventAutoArchiveLog::create([
                                'event_id' => $event->id, 'event_title' => $event->title,
                                'event_ended_at' => $endedAt, 'cutoff_at' => $cutoff, 'archived_at' => $archivedAt,
                            ]);
                        }

                        return true;
                    }, 3);
                }
            });

        return $count;
    }

    private function endedAt(Event $event): ?CarbonImmutable
    {
        // Retain the existing audit column for the terminal retention anchor:
        // actual event end for completed events; cancellation time otherwise.
        if ($event->isCancelled()) {
            return $event->cancelled_at ? CarbonImmutable::instance($event->cancelled_at) : null;
        }
        $date = $event->event_end_date ?? $event->event_date;
        if (! $date) {
            return null;
        }
        $day = CarbonImmutable::parse($date->toDateString(), config('app.timezone'));

        // Legacy events without an end time retain the existing end-of-day rule.
        return $event->end_time ? $day->setTimeFromTimeString($event->end_time->format('H:i:s')) : $day->endOfDay();
    }
}
