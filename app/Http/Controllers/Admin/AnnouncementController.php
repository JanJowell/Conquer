<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->with('event')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereHas('event', function ($eventQuery) use ($user) {
                    $eventQuery->where('manager_id', $user->id);
                });
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        $user = auth()->user();
        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get();

        return view('admin.announcements.create', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accessibleEventIds = $this->accessibleEventIds($request);

        $validated = $request->validate([
            'event_id' => ['nullable', Rule::in($accessibleEventIds)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        Announcement::create([
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_published' => (bool) ($validated['is_published'] ?? true),
            'published_at' => ($validated['is_published'] ?? true) ? now() : null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement created successfully.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted successfully.');
    }

    private function accessibleEventIds(Request $request): array
    {
        $user = $request->user();

        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function canAccessAnnouncement(Announcement $announcement): bool
    {
        if (! $announcement->event) {
            return ! auth()->user()->managesAssignedEventsOnly();
        }

        return auth()->user()->canManageEvent($announcement->event);
    }
}
