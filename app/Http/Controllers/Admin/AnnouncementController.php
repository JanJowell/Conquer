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
            ->when($request->input('status') === 'published', function ($query) {
                $query->active();
            })
            ->when($request->input('status') === 'draft', function ($query) {
                $query->where('is_published', false);
            })
            ->when($request->input('status') === 'expired', function ($query) {
                $query->expired();
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $events = $this->eventOptions($user);

        $summaryBaseQuery = Announcement::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereHas('event', function ($eventQuery) use ($user) {
                    $eventQuery->where('manager_id', $user->id);
                });
            });

        $summary = [
            'published' => (clone $summaryBaseQuery)->active()->count(),
            'drafts' => (clone $summaryBaseQuery)->where('is_published', false)->count(),
            'expired' => (clone $summaryBaseQuery)->expired()->count(),
            'general' => (clone $summaryBaseQuery)->whereNull('event_id')->count(),
            'event_specific' => (clone $summaryBaseQuery)->whereNotNull('event_id')->count(),
        ];

        return view('admin.announcements.index', compact('announcements', 'events', 'summary'));
    }

    public function create(): View
    {
        $user = auth()->user();
        $events = $this->eventOptions($user);
        $canCreateGeneral = ! $user->managesAssignedEventsOnly();

        return view('admin.announcements.create', compact('events', 'canCreateGeneral'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAnnouncement($request);

        $isPublished = $request->boolean('is_published');

        Announcement::create([
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement created successfully.');
    }

    public function edit(Announcement $announcement): View
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $user = auth()->user();
        $events = $this->eventOptions($user);
        $canCreateGeneral = ! $user->managesAssignedEventsOnly();

        return view('admin.announcements.edit', compact('announcement', 'events', 'canCreateGeneral'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $validated = $this->validateAnnouncement($request);
        $wasPublished = $announcement->is_published;
        $isPublished = $request->boolean('is_published');

        $announcement->update([
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_published' => $isPublished,
            'published_at' => $isPublished
                ? ($wasPublished ? ($announcement->published_at ?? now()) : now())
                : null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated successfully.');
    }

    public function publish(Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $announcement->update([
            'is_published' => true,
            'published_at' => $announcement->published_at ?? now(),
        ]);

        return back()->with('success', 'Announcement published successfully.');
    }

    public function unpublish(Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $announcement->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        return back()->with('success', 'Announcement moved back to drafts.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        abort_unless($this->canAccessAnnouncement($announcement), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted successfully.');
    }

    private function validateAnnouncement(Request $request): array
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($request);

        return $request->validate([
            'event_id' => [
                Rule::requiredIf($user->managesAssignedEventsOnly()),
                'nullable',
                Rule::in($accessibleEventIds),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }

    private function eventOptions($user)
    {
        return Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title', 'event_date']);
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
