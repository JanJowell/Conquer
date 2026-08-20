<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\PushNotification;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Services\EBadgeAutoIssuer;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventOperationsController extends Controller
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging) {}

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

        $categories = Category::query()
            ->with('event:id,title')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->orderBy('event_id')
            ->orderBy('name')
            ->get(['id', 'event_id', 'name']);

        $participants = $this->filteredParticipantQuery($request)
            ->with(['issuedEBadges.badge'])
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
            'rejected' => $this->registrationBaseQuery($user)->where('status', 'rejected')->count(),
        ];

        $statusOptions = $this->registrationStatuses();

        return view('admin.participants.index', compact('participants', 'events', 'categories', 'summary', 'statusOptions'));
    }

    public function exportParticipants(Request $request): StreamedResponse
    {
        $user = $request->user();
        $accessibleEventIds = $this->accessibleEventIds($user);
        $event = $request->filled('event_id')
            ? Event::query()->whereIn('id', $accessibleEventIds)->find($request->integer('event_id'))
            : null;
        $category = $request->filled('category_id')
            ? Category::query()->whereIn('event_id', $accessibleEventIds)->find($request->integer('category_id'))
            : null;

        $nameParts = [
            $event ? Str::slug($event->title) : 'all-events',
            $category ? Str::slug($category->name) : null,
            'participants',
            now()->format('Y-m-d-His'),
        ];
        $filename = implode('-', array_filter($nameParts)).'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open the participant export stream.');
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Registration ID',
                'Bib Number',
                'Participant Name',
                'Email',
                'Phone',
                'Event',
                'Category',
                'Scheduled Category Start',
                'Shirt Size',
                'Registration Status',
                'Payment Status',
                'Payment Amount',
                'Currency',
                'Category Start',
                'Finish Time',
                'Category Rank',
                'Overall Rank',
                'Registered At',
            ]);

            $this->filteredParticipantQuery($request)
                ->orderBy('id')
                ->chunkById(200, function ($registrations) use ($handle) {
                    foreach ($registrations as $registration) {
                        $values = [
                            $registration->id,
                            $registration->bib_number,
                            $registration->user?->name,
                            $registration->user?->email,
                            $registration->user?->phone,
                            $registration->event?->title,
                            $registration->category?->name,
                            optional($registration->category?->scheduledStartAt())?->format('Y-m-d H:i:s'),
                            $registration->shirt_size,
                            $registration->status,
                            $registration->payment_status,
                            number_format(($registration->payment_amount_cents ?? 0) / 100, 2, '.', ''),
                            $registration->payment_currency ?? 'PHP',
                            optional($registration->category?->started_at)?->format('Y-m-d H:i:s'),
                            $registration->raceResult?->finish_time,
                            $registration->raceResult?->rank_category,
                            $registration->raceResult?->rank_overall,
                            optional($registration->registered_at ?? $registration->created_at)?->format('Y-m-d H:i:s'),
                        ];

                        fputcsv($handle, array_map(fn ($value) => $this->safeCsvValue($value), $values));
                    }
                }, 'id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateParticipant(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($this->canAccessRegistration($registration), 403);

        $previousStatus = $registration->status;

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->registrationStatuses()))],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
        ]);

        if (in_array($registration->status, ['checked_in', 'completed'], true)) {
            return back()->with('error', 'Checked-in or completed participants must be updated from Check-in or Results.');
        }

        if (! array_key_exists($validated['status'], $this->participantStatuses())) {
            return back()->with('error', 'Participants can only be marked pending, approved, or rejected here.');
        }

        if ($validated['status'] === 'approved'
            && $registration->payment_required
            && ! in_array($registration->payment_status, ['paid', 'waived'], true)) {
            return back()->with('error', 'This registration requires payment before it can be approved.');
        }

        $validated['rejection_reason'] = blank($validated['rejection_reason'] ?? null)
            ? null
            : trim($validated['rejection_reason']);

        try {
            DB::transaction(function () use ($registration, $validated) {
                if ($validated['status'] === 'approved' && blank($registration->bib_number)) {
                    $validated['bib_number'] = $this->nextBibNumberForEvent($registration->event_id);
                } elseif ($validated['status'] === 'rejected') {
                    $validated['bib_number'] = null;
                }

                if ($validated['status'] !== 'rejected') {
                    $validated['rejection_reason'] = null;
                }

                $registration->update($validated);
            });
        } catch (QueryException) {
            return back()
                ->withInput()
                ->with('error', 'That bib number was just assigned. Please save again to get the next available number.');
        }

        $updatedRegistration = $registration->fresh(['user', 'event', 'category']);

        if ($previousStatus !== $updatedRegistration->status
            && in_array($updatedRegistration->status, ['approved', 'rejected'], true)) {
            $this->notifyRunnerAboutRegistrationStatus($updatedRegistration);
        }

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
            'status' => ['required', Rule::in(['approved', 'checked_in'])],
            'kit_waiver_signed' => ['nullable', 'boolean'],
        ]);

        if ($registration->status === 'completed') {
            return back()->with('error', 'Completed participants must be updated from Results.');
        }

        if (! in_array($registration->status, ['approved', 'checked_in'], true)) {
            return back()->with('error', 'Only approved or checked-in participants can be updated from Check-in.');
        }

        $registration->loadMissing('event');

        if ($registration->event?->effective_status === 'completed') {
            return back()->with('error', 'Check-in is closed because this event is already completed.');
        }

        if ($validated['status'] === 'checked_in'
            && ! $registration->waiver_accepted
            && ! $registration->kit_waiver_signed_at
            && ! $request->boolean('kit_waiver_signed')) {
            return back()->with('error', 'The participant must sign the waiver before the race kit can be released.');
        }

        try {
            DB::transaction(function () use ($request, $registration, $validated) {
                $updates = ['status' => $validated['status']];

                if ($validated['status'] === 'checked_in' && blank($registration->bib_number)) {
                    $updates['bib_number'] = $this->nextBibNumberForEvent($registration->event_id);
                }

                if ($validated['status'] === 'checked_in') {
                    if (! $registration->kit_released_at) {
                        $updates['kit_released_at'] = now();
                    }

                    if (! $registration->waiver_accepted && ! $registration->kit_waiver_signed_at && $request->boolean('kit_waiver_signed')) {
                        $updates['kit_waiver_signed_at'] = now();
                    }
                } elseif ($validated['status'] === 'approved') {
                    $updates['kit_released_at'] = null;
                }

                $registration->update($updates);
            });
        } catch (QueryException) {
            return back()
                ->withInput()
                ->with('error', 'A bib number was just assigned. Please save again to get the next available number.');
        }

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
            ->with(['user', 'event', 'category', 'raceResult', 'issuedEBadges.badge'])
            ->whereIn('status', ['checked_in', 'completed'])
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

        $raceCategories = Category::query()
            ->with(['event', 'startedBy'])
            ->withCount([
                'registrations as checked_in_count' => fn ($query) => $query->whereIn('status', ['checked_in', 'completed']),
                'raceResults',
            ])
            ->whereIn('event_id', $accessibleEventIds)
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->where(function ($query) {
                $query->whereNotNull('started_at')
                    ->orWhereHas('registrations', fn ($registrationQuery) => $registrationQuery->whereIn('status', ['checked_in', 'completed']));
            })
            ->orderBy('event_id')
            ->orderBy('distance_km')
            ->get();

        $summary = [
            'published_results' => $this->raceResultBaseQuery($user)->count(),
            'awaiting_results' => $this->registrationBaseQuery($user)->where('status', 'checked_in')->doesntHave('raceResult')->count(),
            'completed_registrations' => $this->registrationBaseQuery($user)->where('status', 'completed')->count(),
        ];

        $badges = EBadge::query()
            ->where('is_active', true)
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->orderBy('title')
            ->get(['id', 'event_id', 'category_id', 'title', 'auto_issue_rule']);

        return view('admin.results.index', compact('registrations', 'events', 'summary', 'badges', 'raceCategories'));
    }

    public function storeResult(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'registration_id' => ['required', 'exists:registrations,id'],
            'finish_time' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! $this->isValidFinishTime($value)) {
                    $fail('Finish time must use MM:SS or HH:MM:SS format.');
                }
            }],
            'finish_now' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $registration = Registration::with(['user', 'event', 'category'])->findOrFail($validated['registration_id']);
        abort_unless($this->canAccessRegistration($registration), 403);

        if (! $registration->category_id || ! $this->categoryBelongsToEvent($registration->category_id, $registration->event_id)) {
            return back()
                ->withInput()
                ->with('error', 'The participant must have a valid registered category before saving a result.');
        }

        if (! in_array($registration->status, ['checked_in', 'completed'], true)) {
            return back()
                ->withInput()
                ->with('error', 'Only checked-in or completed participants can receive race results.');
        }

        if ($request->boolean('finish_now')) {
            $finishTime = $this->finishTimeFromCategoryStart($registration);

            if (! $finishTime) {
                return back()
                    ->withInput()
                    ->with('error', 'Start the participant category before using Finish.');
            }

            $validated['finish_time'] = $finishTime;
        }

        unset($validated['finish_now']);

        if (! $this->hasResultPayload($validated)) {
            return back()
                ->withInput()
                ->with('error', 'Enter a finish time or remark before saving a result.');
        }

        DB::transaction(function () use ($registration, $validated) {
            RaceResult::updateOrCreate(
                ['registration_id' => $registration->id],
                [
                    'user_id' => $registration->user_id,
                    'event_id' => $registration->event_id,
                    'category_id' => $registration->category_id,
                    'finish_time' => $this->normalizeFinishTime($validated['finish_time'] ?? null),
                    'rank_overall' => null,
                    'rank_category' => null,
                    'remarks' => $validated['remarks'] ?? null,
                ]
            );

            $registration->update(['status' => 'completed']);
            $this->recalculateEventRanks($registration->event_id);
        });

        $this->issueAutomaticBadgesForEvent($registration->event_id);

        return back()->with('success', 'Race result saved and rankings recalculated successfully.');
    }

    public function updateResult(Request $request, RaceResult $result): RedirectResponse
    {
        abort_unless($result->registration && $this->canAccessRegistration($result->registration), 403);

        $validated = $request->validate([
            'finish_time' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! $this->isValidFinishTime($value)) {
                    $fail('Finish time must use MM:SS or HH:MM:SS format.');
                }
            }],
            'finish_now' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $resultCategoryId = $result->registration?->category_id ?? $result->category_id;

        if (! $resultCategoryId || ! $this->categoryBelongsToEvent($resultCategoryId, $result->registration->event_id)) {
            return back()
                ->withInput()
                ->with('error', 'The participant must have a valid registered category before saving a result.');
        }

        if ($request->boolean('finish_now')) {
            $finishTime = $this->finishTimeFromCategoryStart($result->registration);

            if (! $finishTime) {
                return back()
                    ->withInput()
                    ->with('error', 'Start the participant category before using Finish.');
            }

            $validated['finish_time'] = $finishTime;
        }

        unset($validated['finish_now']);

        if (array_key_exists('finish_time', $validated)) {
            $validated['finish_time'] = $this->normalizeFinishTime($validated['finish_time']);
        }

        if (! $this->hasResultPayload($validated)) {
            return back()
                ->withInput()
                ->with('error', 'Enter a finish time or remark before saving a result.');
        }

        $validated['category_id'] = $resultCategoryId;

        DB::transaction(function () use ($result, $validated) {
            $result->update([
                ...$validated,
                'rank_overall' => null,
                'rank_category' => null,
            ]);

            if ($result->registration) {
                $result->registration->update(['status' => 'completed']);
            }

            $this->recalculateEventRanks($result->event_id);
        });

        $this->issueAutomaticBadgesForEvent($result->event_id);

        return back()->with('success', 'Race result updated and rankings recalculated successfully.');
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

    private function filteredParticipantQuery(Request $request)
    {
        $user = $request->user();

        return Registration::query()
            ->with(['user', 'event', 'category', 'raceResult'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereIn('event_id', $user->managedEventIds());
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->integer('category_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();

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
            });
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function participantStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
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

    private function notifyRunnerAboutRegistrationStatus(Registration $registration): void
    {
        if (! $registration->user) {
            return;
        }

        $copy = $this->registrationStatusNotificationCopy($registration);

        if (! $copy) {
            return;
        }

        $notification = PushNotification::create([
            'title' => $copy['title'],
            'message' => $copy['message'],
            'type' => 'payment',
            'target_audience' => 'runners',
            'target_user_id' => $registration->user_id,
            'data' => [
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
                'category_id' => (string) $registration->category_id,
                'registration_status' => (string) $registration->status,
                'payment_status' => (string) $registration->payment_status,
                'screen' => 'registration',
            ],
            'is_active' => true,
        ]);

        try {
            $result = $this->messaging->sendNotification($notification, collect([$registration->user]));

            if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
                $notification->update(['sent_at' => now()]);
            } elseif (! ($result['retry'] ?? false)) {
                $notification->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('Registration status notification could not be delivered immediately.', [
                'registration_id' => $registration->id,
                'registration_status' => $registration->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function registrationStatusNotificationCopy(Registration $registration): ?array
    {
        $eventTitle = $registration->event?->title ?: 'your event';

        return match ($registration->status) {
            'approved' => [
                'title' => 'Registration Approved',
                'message' => "Your registration for {$eventTitle} has been approved.",
            ],
            'rejected' => [
                'title' => 'Registration Rejected',
                'message' => filled($registration->rejection_reason)
                    ? "Your registration for {$eventTitle} was rejected: {$registration->rejection_reason}"
                    : "Your registration for {$eventTitle} was rejected.",
            ],
            default => null,
        };
    }

    private function hasResultPayload(array $data): bool
    {
        return filled($data['finish_time'] ?? null)
            || filled($data['remarks'] ?? null);
    }

    private function recalculateEventRanks(int $eventId): void
    {
        $results = RaceResult::query()
            ->where('event_id', $eventId)
            ->lockForUpdate()
            ->get(['id', 'category_id', 'finish_time', 'rank_overall', 'rank_category']);

        $rankUpdates = $results
            ->mapWithKeys(fn (RaceResult $result) => [
                $result->id => [
                    'rank_overall' => null,
                    'rank_category' => null,
                ],
            ])
            ->all();

        $finishedResults = $results
            ->filter(fn (RaceResult $result) => $this->finishTimeSeconds($result->finish_time) !== null)
            ->sort(function (RaceResult $first, RaceResult $second) {
                $timeComparison = $this->finishTimeSeconds($first->finish_time) <=> $this->finishTimeSeconds($second->finish_time);

                return $timeComparison !== 0 ? $timeComparison : $first->id <=> $second->id;
            })
            ->values();

        foreach ($finishedResults as $index => $result) {
            $rankUpdates[$result->id]['rank_overall'] = $index + 1;
        }

        $finishedResults
            ->groupBy('category_id')
            ->each(function ($categoryResults) use (&$rankUpdates) {
                $categoryResults->values()->each(function (RaceResult $result, int $index) use (&$rankUpdates) {
                    $rankUpdates[$result->id]['rank_category'] = $index + 1;
                });
            });

        foreach ($results as $result) {
            $updates = $rankUpdates[$result->id];

            if ((int) $result->rank_overall !== (int) $updates['rank_overall']
                || (int) $result->rank_category !== (int) $updates['rank_category']) {
                RaceResult::query()
                    ->whereKey($result->id)
                    ->update([
                        'rank_overall' => $updates['rank_overall'],
                        'rank_category' => $updates['rank_category'],
                    ]);
            }
        }
    }

    private function issueAutomaticBadgesForEvent(int $eventId): void
    {
        app(EBadgeAutoIssuer::class)->syncForCompletedRegistrationsInEvent($eventId);
    }

    private function categoryBelongsToEvent(mixed $categoryId, int $eventId): bool
    {
        return Category::query()
            ->whereKey($categoryId)
            ->where('event_id', $eventId)
            ->exists();
    }

    private function nextBibNumberForEvent(int $eventId): string
    {
        $highestBib = Registration::query()
            ->where('event_id', $eventId)
            ->whereNotNull('bib_number')
            ->lockForUpdate()
            ->pluck('bib_number')
            ->map(fn ($bibNumber) => trim((string) $bibNumber))
            ->filter(fn ($bibNumber) => ctype_digit($bibNumber))
            ->map(fn ($bibNumber) => (int) $bibNumber)
            ->max() ?? 0;

        return str_pad((string) ($highestBib + 1), 3, '0', STR_PAD_LEFT);
    }

    private function finishTimeFromCategoryStart(Registration $registration): ?string
    {
        $registration->loadMissing('category');

        if (! $registration->category?->started_at) {
            return null;
        }

        $startAt = $registration->category->started_at;

        if (now()->lt($startAt)) {
            return null;
        }

        return $this->formatDurationSeconds($startAt->diffInSeconds(now()));
    }

    private function formatDurationSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    private function isValidFinishTime(?string $finishTime): bool
    {
        if ($finishTime === null || trim($finishTime) === '') {
            return true;
        }

        $finishTime = trim($finishTime);

        return preg_match('/^\d+:[0-5]\d$/', $finishTime) === 1
            || preg_match('/^\d+:[0-5]\d:[0-5]\d$/', $finishTime) === 1;
    }

    private function normalizeFinishTime(?string $finishTime): ?string
    {
        if ($finishTime === null || trim($finishTime) === '') {
            return null;
        }

        $parts = array_map('intval', explode(':', trim($finishTime)));

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;
            $hours = intdiv($minutes, 60);
            $minutes = $minutes % 60;
        } else {
            [$hours, $minutes, $seconds] = $parts;
        }

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function finishTimeSeconds(?string $finishTime): ?int
    {
        if ($finishTime === null || trim($finishTime) === '') {
            return null;
        }

        $parts = array_map('intval', explode(':', trim($finishTime)));

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;

            return ($minutes * 60) + $seconds;
        }

        if (count($parts) === 3) {
            [$hours, $minutes, $seconds] = $parts;

            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return null;
    }
}
