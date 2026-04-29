<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $this->completePastUpcomingEvents($user);

        $events = Event::query()
            ->with(['manager'])
            ->withCount(['categories', 'registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('organized_by', 'like', "%{$search}%");
                });
            })
            ->orderBy('event_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.events.index', compact('events'));
    }

    public function create(): View
    {
        $managers = User::query()
            ->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.events.create', compact('managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'registration_deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published,ongoing,completed,upcoming'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'organized_by' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        if ($user->managesAssignedEventsOnly()) {
            $validated['manager_id'] = $user->id;
        }

        Event::create([
            ...$validated,
            'slug' => Str::slug($validated['title'] . '-' . time()),
        ]);

        return redirect()->route('admin.events.index')->with('success', 'Event created successfully.');
    }

    public function edit(Event $event): View
    {
        abort_unless($this->canAccessEvent($event), 403);

        $managers = User::query()
            ->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.events.edit', compact('event', 'managers'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canAccessEvent($event), 403);

        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'registration_deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published,ongoing,completed,upcoming'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'organized_by' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        if ($user->managesAssignedEventsOnly()) {
            $validated['manager_id'] = $user->id;
        }

        $event->update([
            ...$validated,
            'slug' => Str::slug($validated['title'] . '-' . $event->id),
        ]);

        return redirect()->route('admin.events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        abort_unless($this->canAccessEvent($event), 403);

        $event->delete();

        return back()->with('success', 'Event deleted successfully.');
    }

    private function canAccessEvent(Event $event): bool
    {
        return auth()->user()->canManageEvent($event);
    }

    private function completePastUpcomingEvents(User $user): void
    {
        Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->where('status', 'upcoming')
            ->whereDate('event_date', '<', today())
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
    }
}
