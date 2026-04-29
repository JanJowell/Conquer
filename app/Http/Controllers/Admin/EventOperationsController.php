<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventOperationsController extends Controller
{
    public function participants(Request $request): View
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($user);

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title']);

        $participants = Registration::query()
            ->with(['user', 'event', 'category', 'raceResult'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('bib_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('event', function ($eventQuery) use ($search) {
                            $eventQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total' => $this->registrationBaseQuery($user)->count(),
            'pending' => $this->registrationBaseQuery($user)->where('status', 'pending')->count(),
            'approved' => $this->registrationBaseQuery($user)->where('status', 'approved')->count(),
            'checked_in' => $this->registrationBaseQuery($user)->where('status', 'checked_in')->count(),
            'completed' => $this->registrationBaseQuery($user)->where('status', 'completed')->count(),
        ];

        $statusOptions = $this->registrationStatuses();

        return view('admin.participants.index', compact('participants', 'events', 'summary', 'statusOptions'));
    }

    public function updateParticipant(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($this->canAccessRegistration($registration), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->registrationStatuses()))],
            'bib_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('registrations', 'bib_number')->ignore($registration->id),
            ],
        ]);

        $registration->update($validated);

        return back()->with('success', 'Participant record updated successfully.');
    }

    public function checkIn(Request $request): View
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($user);

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title']);

        $participants = Registration::query()
            ->with(['user', 'event', 'category'])
            ->whereIn('status', ['approved', 'checked_in', 'completed'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('bib_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'ready' => $this->registrationBaseQuery($user)->where('status', 'approved')->count(),
            'checked_in' => $this->registrationBaseQuery($user)->where('status', 'checked_in')->count(),
            'completed' => $this->registrationBaseQuery($user)->where('status', 'completed')->count(),
        ];

        return view('admin.check-in.index', compact('participants', 'events', 'summary'));
    }

    public function updateCheckIn(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($this->canAccessRegistration($registration), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'checked_in', 'completed'])],
        ]);

        $registration->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Check-in status updated successfully.');
    }

    public function results(Request $request): View
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($user);

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title']);

        $registrations = Registration::query()
            ->with(['user', 'event', 'category', 'raceResult'])
            ->whereIn('status', ['approved', 'checked_in', 'completed'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('bib_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('event', function ($eventQuery) use ($search) {
                            $eventQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'published_results' => $this->raceResultBaseQuery($user)->count(),
            'awaiting_results' => $this->registrationBaseQuery($user)->whereIn('status', ['approved', 'checked_in'])->doesntHave('raceResult')->count(),
            'completed_registrations' => $this->registrationBaseQuery($user)->where('status', 'completed')->count(),
        ];

        return view('admin.results.index', compact('registrations', 'events', 'summary'));
    }

    public function storeResult(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'registration_id' => ['required', 'exists:registrations,id'],
            'finish_time' => ['nullable', 'string', 'max:255'],
            'rank_overall' => ['nullable', 'integer', 'min:1'],
            'rank_category' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $registration = Registration::with(['user', 'event', 'category'])->findOrFail($validated['registration_id']);
        abort_unless($this->canAccessRegistration($registration), 403);

        RaceResult::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'category_id' => $registration->category_id,
                'finish_time' => $validated['finish_time'],
                'rank_overall' => $validated['rank_overall'],
                'rank_category' => $validated['rank_category'],
                'remarks' => $validated['remarks'],
            ]
        );

        $registration->update(['status' => 'completed']);

        return back()->with('success', 'Race result saved successfully.');
    }

    public function updateResult(Request $request, RaceResult $result): RedirectResponse
    {
        abort_unless($result->registration && $this->canAccessRegistration($result->registration), 403);

        $validated = $request->validate([
            'finish_time' => ['nullable', 'string', 'max:255'],
            'rank_overall' => ['nullable', 'integer', 'min:1'],
            'rank_category' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $result->update($validated);

        if ($result->registration) {
            $result->registration->update(['status' => 'completed']);
        }

        return back()->with('success', 'Race result updated successfully.');
    }

    private function registrationStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'checked_in' => 'Checked In',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
        ];
    }

    private function accessibleEventIds($user): array
    {
        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function registrationBaseQuery($user)
    {
        return Registration::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            });
    }

    private function raceResultBaseQuery($user)
    {
        return RaceResult::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            });
    }

    private function canAccessRegistration(Registration $registration): bool
    {
        return $registration->event && auth()->user()->canManageEvent($registration->event);
    }
}
