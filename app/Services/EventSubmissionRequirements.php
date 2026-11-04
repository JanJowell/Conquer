<?php

namespace App\Services;

use App\Models\Event;
use App\Rules\CategoryCourseMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/** New review requirements, deliberately separate from public visibility. */
class EventSubmissionRequirements
{
    public function hasDrawnMap(mixed $value): bool
    {
        return Validator::make(['course_map' => $value], [
            'course_map' => ['required', new CategoryCourseMap],
        ])->passes() && CategoryCourseMap::normalize($value) !== null;
    }

    public function errors(Event $event): array
    {
        $errors = [];
        if (blank($event->end_time)) {
            $errors[] = 'set an event end time';
        }

        $start = $event->event_date && $event->start_time
            ? Carbon::parse($event->event_date->format('Y-m-d').' '.$event->start_time->format('H:i:s'), config('app.timezone')) : null;
        $endDate = $event->event_end_date ?? $event->event_date;
        $end = $endDate && $event->end_time
            ? Carbon::parse($endDate->format('Y-m-d').' '.$event->end_time->format('H:i:s'), config('app.timezone')) : null;
        if ($start && $end && $end->lte($start)) {
            $errors[] = 'set an event end date and time after its start';
        }
        if (! $event->exists) {
            return $errors;
        }

        // Check persisted rows as well as request data: editing is additive, so
        // omitting an existing category cannot bypass submission requirements.
        foreach ($event->categories()->orderBy('id')->get() as $category) {
            if ($category->slot_limit === null || $category->slot_limit < 1) {
                $errors[] = 'set a positive slot limit for '.$category->name;
            }
            if (blank($category->checkpoint_map_image) && ! $this->hasDrawnMap($category->course_map)) {
                $errors[] = 'upload a map image or save a valid drawn route for '.$category->name;
            }
            $category->setRelation('event', $event);
            $categoryStart = $category->scheduledStartAt();
            $categoryEnd = $category->scheduledEndAt();
            if ($start && $end && $categoryStart && $categoryEnd
                && ($categoryStart->lt($start) || $categoryEnd->gt($end) || $categoryEnd->lte($categoryStart))) {
                $errors[] = 'keep the schedule for '.$category->name.' within the event start and end';
            }
        }

        return $errors;
    }
}
