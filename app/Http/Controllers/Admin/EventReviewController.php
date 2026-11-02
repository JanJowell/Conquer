<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventReviewWorkflow;
use App\Services\EventRevisionComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventReviewController extends Controller
{
    public function transition(Request $request, Event $event, string $action, EventReviewWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['review_version' => ['required', 'integer', 'min:0'],
            'reason' => [$action === 'return' ? 'required' : 'nullable', 'string', 'max:2000']]);
        $event = $workflow->transition($event, $request->user(), $action, (int) $data['review_version'], $data['reason'] ?? null);

        $destination = $action === 'return' && $event->revision_of_id && $request->user()->isSuperAdmin()
            ? 'admin.events.review-history' : 'admin.events.show';

        return to_route($destination, $event)->with('success', match ($action) {
            'approve' => 'Event approved and published. The reviewed version is now public.',
            'return' => 'Submission returned to the Event Manager with your explanation.',
            'withdraw' => 'Submission withdrawn. You can edit and resubmit the draft.',
            'discard' => 'Revision discarded. The published event is unchanged; you may start a fresh revision.',
            default => 'Event submitted for Admin review. It is not public until approved.',
        });
    }

    public function revision(Request $request, Event $event, EventReviewWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['review_version' => ['required', 'integer', 'min:0']]);
        $draft = $workflow->startRevision($event, $request->user(), (int) $data['review_version']);

        return to_route('admin.events.edit', $draft)->with('success', 'Private revision opened. The current published event remains unchanged until Admin approval.');
    }

    public function history(Request $request, Event $event, EventRevisionComparison $comparison): View
    {
        $user = $request->user();
        abort_unless(($user->isSuperAdmin() || $user->canManageEvent($event))
            && $event->reviews()->where('action', 'submit')->exists(), 404);
        $entries = $event->reviews()->where('action', '!=', 'revision_created')->with('actor')->get()
            ->map(function ($review) use ($comparison) {
                $snapshot = $review->submission_snapshot;
                $display = $snapshot ? ['available' => true, 'stale' => false, 'applied' => false,
                    'sections' => $comparison->compareSnapshots($snapshot['published_setup']
                        ?? ['event' => [], 'categories' => [], 'paymentMethods' => [], 'checkpoints' => []],
                        $snapshot['setup'], $snapshot['maps'])] : null;

                return ['review' => $review, 'comparison' => $display];
            });
        $backEventId = $event->revision_of_id ?: $event->id;

        return view('admin.events.review-history', compact('entries', 'backEventId'));
    }
}
