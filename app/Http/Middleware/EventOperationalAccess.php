<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\FinishScanToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EventOperationalAccess
{
    public function handle(Request $request, Closure $next, string $operation = 'write'): Response
    {
        $subject = $request->route('event') ?? $request->route('category') ?? $request->route('registration') ?? $request->route('result');
        if ($operation === 'finish') {
            if (! $request->user()?->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
                return $next($request);
            }
            $token = $request->input('token');
            $subject = is_string($token) && strlen($token) <= 2048 ? app(FinishScanToken::class)->resolve($token) : null;
        }
        if (! $subject && $operation === 'result') {
            $id = filter_var($request->input('registration_id'), FILTER_VALIDATE_INT);
            $subject = $id ? Registration::find($id) : null;
        }
        $eventId = match (true) {
            $subject instanceof Event => $subject->revision_of_id ?: $subject->id,
            $subject instanceof Category, $subject instanceof Registration, $subject instanceof RaceResult => $subject->event_id,
            default => null,
        };
        if (! $eventId) {
            return $next($request); // Existing controllers validate malformed requests.
        }

        // One attempt: never repeat an external checkout call after a deadlock.
        return DB::transaction(function () use ($request, $next, $operation, $eventId, $subject) {
            $event = Event::query()->whereKey($eventId)->sharedLock()->first();
            if (! $event) {
                return $next($request);
            }
            if ($request->user()?->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])
                && ! $request->user()->canManageEvent($event)) {
                return $next($request);
            }
            if ($subject instanceof Registration && $subject->user_id !== $request->user()?->id
                && ! $request->user()?->canManageEvent($event)) {
                return $next($request); // Preserve the endpoint's ownership denial.
            }
            // Finish retries acknowledge accepted history. Existing result
            // corrections remain possible, but "Finish Now" is a new capture.
            $historicalOperation = $operation === 'finish'
                || ($operation === 'result-edit' && ! $request->boolean('finish_now'));
            if ($event->isCancelled() && ! $historicalOperation) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'This event has been cancelled. New registrations, payments and race operations are closed.', 'code' => 'event_cancelled'], 409)
                    : back()->with('error', 'This event has been cancelled. New participant and race operations are closed.');
            }
            if ($subject instanceof Event && ! $subject->revision_of_id) {
                $request->route()->setParameter('event', $event);
            } elseif ($subject instanceof Category || $subject instanceof Registration) {
                $subject->setRelation('event', $event);
            }

            return $next($request);
        });
    }
}
