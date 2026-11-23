<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventCleanup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventCleanupController extends Controller
{
    public function __construct(private readonly EventCleanup $cleanup) {}

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        return $this->apply($request, $event, 'delete');
    }

    public function archive(Request $request, Event $event): RedirectResponse
    {
        return $this->apply($request, $event, 'archive');
    }

    public function restore(Request $request, Event $event): RedirectResponse
    {
        return $this->apply($request, $event, 'restore');
    }

    private function apply(Request $request, Event $event, string $action): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $input = $request->validate([
            'cleanup_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'confirmation' => ['required', 'accepted'],
            'confirmation_title' => [$action === 'delete' ? 'required' : 'nullable', 'string', 'max:255'],
        ]);
        $this->cleanup->perform($event, $request->user(), $action, $input['cleanup_fingerprint'], $input['confirmation_title'] ?? null, $request->ip() ?? 'unknown');

        return redirect()->route('admin.events.index', ['archive' => $action === 'archive' ? 'archived' : 'active'])
            ->with('success', match ($action) {
                'delete' => 'Empty draft deleted permanently.',
                'archive' => 'Event archived from the management list. Participant activity and all event history remain available.',
                'restore' => 'Event restored to the management list. Its status and history are unchanged.',
            });
    }
}
