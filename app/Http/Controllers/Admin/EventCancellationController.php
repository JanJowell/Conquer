<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventCancellation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventCancellationController extends Controller
{
    public function __invoke(Request $request, Event $event, EventCancellation $cancellation): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $input = $request->validate(['cancellation_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'confirmation' => ['required', 'accepted'],
            'cancellation_reason' => ['required', 'string', 'max:1000']]);
        $cancellation->cancel($event, $request->user(), $input['cancellation_fingerprint'],
            $input['cancellation_reason'], $request->ip() ?? 'unknown');

        return redirect()->route('admin.events.index')->with('success', 'Event cancelled. Its manager is available for new bookings. Existing participant, payment and race records are preserved; no refund was issued.');
    }
}
