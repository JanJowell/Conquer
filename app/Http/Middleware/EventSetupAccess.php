<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Checkpoint;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventSetupAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->managesAssignedEventsOnly(), 403, 'Event setup belongs to its Event Manager.');
        $item = $request->route('event') ?? $request->route('category') ?? $request->route('checkpoint');
        $eventId = $item instanceof Event ? $item->id
            : (($item instanceof Category || $item instanceof Checkpoint) ? $item->event_id : $request->input('event_id'));
        if (! $eventId) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $eventId, $item) {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            abort_unless($event->canEditSetup($request->user()), 403,
                'Setup is locked. Withdraw a submission or open a reviewed revision of a published event.');
            if ($item instanceof Event) {
                $request->route()->setParameter('event', $event);
            }
            if ($item instanceof Checkpoint && $request->has('event_id')) {
                abort_unless((int) $request->input('event_id') === (int) $event->id, 403, 'A checkpoint cannot move between events.');
            }
            // Existing incomplete drafts enter the workflow on their first setup write.
            if (! $request->isMethod('GET') && $event->review_status === null) {
                $event->forceFill(['review_status' => 'draft'])->save();
            }

            return $next($request);
        });
    }
}
