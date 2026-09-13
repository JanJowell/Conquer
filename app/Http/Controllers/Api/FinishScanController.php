<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\Registration;
use App\Models\User;
use App\Services\FinishScanToken;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinishScanController extends Controller
{
    public function __construct(private readonly FinishScanToken $tokens) {}

    public function context(Request $request): JsonResponse
    {
        $staff = $this->authorizedStaff($request);

        if (! $staff) {
            return $this->forbidden();
        }

        $events = Event::query()
            ->when($staff->managesAssignedEventsOnly(), fn ($query) => $query->where('manager_id', $staff->id))
            ->whereHas('categories', fn ($query) => $query->whereHas('registrations', fn ($registrations) => $registrations->whereIn('status', ['checked_in', 'completed'])))
            ->with(['categories' => function ($query) {
                $query->withCount([
                    'registrations as checked_in_count' => fn ($registrations) => $registrations->where('status', 'checked_in'),
                    'finishScans as finish_scans_count',
                ])->whereHas('registrations', fn ($registrations) => $registrations->whereIn('status', ['checked_in', 'completed']))
                    ->orderBy('name');
            }])
            ->orderByDesc('event_date')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'categories' => $event->categories->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'started' => $category->started_at !== null,
                    'started_at' => $category->started_at?->toIso8601String(),
                    'scheduled_end_at' => $category->scheduledEndAt()?->toIso8601String(),
                    'checked_in_count' => $category->checked_in_count,
                    'finish_scans_count' => $category->finish_scans_count,
                ])->values(),
            ])->values();

        return response()->json(['data' => $events]);
    }

    public function index(Request $request): JsonResponse
    {
        $staff = $this->authorizedStaff($request);

        if (! $staff) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $scans = FinishScan::query()
            ->with(['registration.user', 'event', 'category', 'scannedBy'])
            ->when($staff->managesAssignedEventsOnly(), function ($query) use ($staff) {
                $query->whereHas('event', fn ($events) => $events->where('manager_id', $staff->id));
            })
            ->when(isset($validated['event_id']), fn ($query) => $query->where('event_id', $validated['event_id']))
            ->when(isset($validated['category_id']), fn ($query) => $query->where('category_id', $validated['category_id']))
            ->latest('scanned_at')
            ->limit(100)
            ->get()
            ->map(fn (FinishScan $scan) => $this->payload($scan));

        return response()->json(['data' => $scans]);
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $this->authorizedStaff($request);

        if (! $staff) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $registration = $this->tokens->resolve($validated['token']);

        if (! $registration) {
            return $this->error('The BIB QR code is invalid or has been altered.', 'invalid_scan_token', 422);
        }

        if ((isset($validated['event_id']) && (int) $registration->event_id !== (int) $validated['event_id'])
            || (isset($validated['category_id']) && (int) $registration->category_id !== (int) $validated['category_id'])) {
            return $this->error('This BIB belongs to a different event or category.', 'selection_mismatch', 422);
        }

        $registration->loadMissing('event');

        if (! $registration->event || ! $staff->canManageEvent($registration->event)) {
            return $this->error('You are not authorized to scan participants for this event.', 'forbidden_event', 403);
        }

        try {
            $result = DB::transaction(function () use ($registration, $staff) {
                $locked = Registration::query()
                    ->with(['user', 'event', 'category', 'finishScan', 'raceResult'])
                    ->lockForUpdate()
                    ->findOrFail($registration->id);

                if ($locked->finishScan) {
                    return ['error' => $this->duplicatePayload($locked->finishScan), 'status' => 409];
                }

                if ($locked->raceResult || $locked->status === 'completed') {
                    return ['error' => $this->errorPayload('This participant already has an official result.', 'result_already_published'), 'status' => 409];
                }

                if ($locked->status !== 'checked_in') {
                    return ['error' => $this->errorPayload('Only checked-in participants can receive a finish scan.', 'participant_not_checked_in'), 'status' => 409];
                }

                if (! $locked->bib_number) {
                    return ['error' => $this->errorPayload('This registration does not have a BIB number.', 'bib_not_assigned'), 'status' => 409];
                }

                if (! $locked->category?->started_at) {
                    return ['error' => $this->errorPayload('Start this category before recording finishes.', 'category_not_started'), 'status' => 409];
                }

                $scannedAt = now();

                if ($scannedAt->lt($locked->category->started_at)) {
                    return ['error' => $this->errorPayload('The recorded category start is in the future.', 'category_not_started'), 'status' => 409];
                }

                $elapsedSeconds = $locked->category->started_at->diffInSeconds($scannedAt);
                $scan = FinishScan::create([
                    'registration_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'event_id' => $locked->event_id,
                    'category_id' => $locked->category_id,
                    'bib_number' => $locked->bib_number,
                    'scanned_at' => $scannedAt,
                    'elapsed_seconds' => $elapsedSeconds,
                    'elapsed_time' => $this->formatDuration($elapsedSeconds),
                    'scanned_by_user_id' => $staff->id,
                    'status' => FinishScan::STATUS_PROVISIONAL,
                ]);

                $scan->setRelation('registration', $locked);
                $scan->setRelation('event', $locked->event);
                $scan->setRelation('category', $locked->category);
                $scan->setRelation('scannedBy', $staff);

                return ['scan' => $scan];
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = FinishScan::query()
                ->with(['registration.user', 'event', 'category', 'scannedBy'])
                ->where('registration_id', $registration->id)
                ->first();

            return $existing
                ? response()->json($this->duplicatePayload($existing), 409)
                : $this->error('The finish scan could not be recorded.', 'scan_conflict', 409);
        }

        if (isset($result['error'])) {
            return response()->json($result['error'], $result['status']);
        }

        return response()->json([
            'message' => 'Finish recorded provisionally. An administrator must publish the official result.',
            'data' => $this->payload($result['scan']),
        ], 201);
    }

    private function authorizedStaff(Request $request): ?User
    {
        $user = $request->user();

        return $user?->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]) ? $user : null;
    }

    private function forbidden(): JsonResponse
    {
        return $this->error('This account is not authorized to use the finish scanner.', 'scanner_forbidden', 403);
    }

    private function duplicatePayload(FinishScan $scan): array
    {
        return [
            'message' => 'This participant already has a recorded finish scan.',
            'code' => 'already_scanned',
            'data' => $this->payload($scan),
        ];
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json($this->errorPayload($message, $code), $status);
    }

    private function errorPayload(string $message, string $code): array
    {
        return ['message' => $message, 'code' => $code];
    }

    private function payload(FinishScan $scan): array
    {
        $scan->loadMissing(['registration.user', 'event', 'category', 'scannedBy']);
        $cutoff = $scan->category?->scheduledEndAt();

        return [
            'id' => $scan->id,
            'registration_id' => $scan->registration_id,
            'participant_name' => $scan->registration?->user?->name,
            'bib_number' => $scan->bib_number,
            'event_id' => $scan->event_id,
            'event_title' => $scan->event?->title,
            'category_id' => $scan->category_id,
            'category_name' => $scan->category?->name,
            'scanned_at' => $scan->scanned_at?->toIso8601String(),
            'elapsed_seconds' => $scan->elapsed_seconds,
            'elapsed_time' => $scan->elapsed_time,
            'status' => $scan->status,
            'after_cutoff' => $cutoff ? $scan->scanned_at?->gt($cutoff) : false,
            'scanned_by' => $scan->scannedBy ? [
                'id' => $scan->scannedBy->id,
                'name' => $scan->scannedBy->name,
            ] : null,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }
}
