<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Carbon;

class EventManagerAvailability
{
    public function conflictingEvent(int $managerId, array $schedule, ?int $exceptEventId = null): ?Event
    {
        $candidate = $this->normalizeSchedule($schedule);

        if ($candidate === null) {
            return null;
        }

        return Event::query()
            ->where('manager_id', $managerId)
            ->when($exceptEventId, fn ($query) => $query->whereKeyNot($exceptEventId))
            ->whereDate('event_date', '<=', $candidate['end_date']->toDateString())
            ->where(function ($query) use ($candidate) {
                $query->whereDate('event_end_date', '>=', $candidate['start_date']->toDateString())
                    ->orWhere(function ($legacyQuery) use ($candidate) {
                        $legacyQuery->whereNull('event_end_date')
                            ->whereDate('event_date', '>=', $candidate['start_date']->toDateString());
                    });
            })
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->first(fn (Event $event) => $this->schedulesOverlap($candidate, $this->normalizeSchedule([
                'event_date' => $event->event_date,
                'event_end_date' => $event->event_end_date,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
            ])));
    }

    public function conflictMessage(Event $event): string
    {
        $startDate = $event->event_date?->format('M j, Y') ?? 'an unscheduled date';
        $endDate = $event->event_end_date?->format('M j, Y') ?? $startDate;
        $dateRange = $startDate === $endDate ? $startDate : "{$startDate} to {$endDate}";
        $timeRange = $event->start_time && $event->end_time
            ? ' from '.$event->start_time->format('g:i A').' to '.$event->end_time->format('g:i A')
            : '';

        return "This event manager is unavailable because they are assigned to \"{$event->title}\" on {$dateRange}{$timeRange}. Choose another manager or change the event schedule.";
    }

    /**
     * @param  array{start_date: Carbon, end_date: Carbon, start_at: ?Carbon, end_at: ?Carbon}|null  $existing
     */
    private function schedulesOverlap(array $candidate, ?array $existing): bool
    {
        if ($existing === null) {
            return false;
        }

        if ($candidate['start_at'] && $candidate['end_at'] && $existing['start_at'] && $existing['end_at']) {
            return $candidate['start_at']->lt($existing['end_at'])
                && $candidate['end_at']->gt($existing['start_at']);
        }

        return $candidate['start_date']->lte($existing['end_date'])
            && $candidate['end_date']->gte($existing['start_date']);
    }

    /**
     * @return array{start_date: Carbon, end_date: Carbon, start_at: ?Carbon, end_at: ?Carbon}|null
     */
    private function normalizeSchedule(array $schedule): ?array
    {
        $startDateValue = $this->dateValue($schedule['event_date'] ?? null);

        if (! $startDateValue) {
            return null;
        }

        $endDateValue = $this->dateValue($schedule['event_end_date'] ?? null) ?: $startDateValue;
        $timezone = config('app.timezone');
        $startDate = Carbon::parse($startDateValue, $timezone)->startOfDay();
        $endDate = Carbon::parse($endDateValue, $timezone)->startOfDay();
        $startTime = $this->timeValue($schedule['start_time'] ?? null);
        $endTime = $this->timeValue($schedule['end_time'] ?? null);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_at' => $startTime ? Carbon::parse("{$startDateValue} {$startTime}", $timezone) : null,
            'end_at' => $endTime ? Carbon::parse("{$endDateValue} {$endTime}", $timezone) : null,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return filled($value) ? Carbon::parse((string) $value)->format('Y-m-d') : null;
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        return filled($value) ? (string) $value : null;
    }
}
