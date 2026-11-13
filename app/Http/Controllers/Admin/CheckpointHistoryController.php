<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CheckpointScan;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckpointHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER]) && ! $user->isSuspended(), 403);

        $filters = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $eventId = filled($filters['event_id'] ?? null) ? (int) $filters['event_id'] : null;
        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        $search = trim($filters['search'] ?? '');

        $visibleEvents = Event::query()->visibleToStaff($user)->whereNull('revision_of_id');
        $visibleEventIds = (clone $visibleEvents)->select('events.id');

        // Scope both rows and filter choices. A forged ID cannot expose another event.
        if ($eventId !== null) {
            (clone $visibleEvents)->findOrFail($eventId);
        }
        if ($categoryId !== null) {
            Category::query()->whereIn('event_id', clone $visibleEventIds)
                ->when($eventId !== null, fn ($query) => $query->where('event_id', $eventId))
                ->findOrFail($categoryId);
        }

        $events = (clone $visibleEvents)->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'title']);
        $categories = Category::query()->whereIn('event_id', clone $visibleEventIds)
            ->orderBy('name')->orderBy('id')->get(['id', 'event_id', 'name']);

        $historyAvailable = Schema::hasTable('checkpoint_scans') && Schema::hasTable('checkpoint_scan_plans');
        $query = CheckpointScan::query()
            ->join('events', 'events.id', '=', 'checkpoint_scans.event_id')
            ->join('categories', function ($join) {
                $join->on('categories.id', '=', 'checkpoint_scans.category_id')
                    ->on('categories.event_id', '=', 'checkpoint_scans.event_id');
            })
            ->whereIn('checkpoint_scans.event_id', clone $visibleEventIds)
            ->when($eventId !== null, fn ($query) => $query->where('checkpoint_scans.event_id', $eventId))
            ->when($categoryId !== null, fn ($query) => $query->where('checkpoint_scans.category_id', $categoryId))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('checkpoint_scans.participant_name', 'like', '%'.$search.'%')
                ->orWhere('checkpoint_scans.bib_number', 'like', '%'.$search.'%')))
            // Do not load QR tokens, request fingerprints, UUIDs or profile data.
            ->select(['checkpoint_scans.id', 'checkpoint_scans.participant_name', 'checkpoint_scans.bib_number',
                'checkpoint_scans.checkpoint_label', 'checkpoint_scans.checkpoint_position',
                'checkpoint_scans.scanned_at', 'checkpoint_scans.elapsed_seconds', 'checkpoint_scans.after_cutoff',
                'checkpoint_scans.scanned_by_name', 'events.title as event_title', 'categories.name as category_name'])
            ->orderByDesc('checkpoint_scans.scanned_at')->orderByDesc('checkpoint_scans.id');
        $scans = $historyAvailable ? $query->paginate(20) : new LengthAwarePaginator([], 0, 20, 1, [
            'path' => route('admin.checkpoint-history.index'),
        ]);
        $scans->appends(array_filter([
            'event_id' => $eventId, 'category_id' => $categoryId, 'search' => $search,
        ], fn ($value) => $value !== null && $value !== ''));

        return response()->view('admin.checkpoint-history.index', compact('scans', 'events', 'categories', 'eventId', 'categoryId', 'search', 'historyAvailable'), $historyAvailable ? 200 : 503)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
