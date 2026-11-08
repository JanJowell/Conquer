<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Rules\CategoryCourseMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StaffCheckpointListController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store', 'Vary' => 'Authorization'];

    public function __invoke(Request $request): JsonResponse
    {
        $staff = $request->user();
        if (! $staff?->hasAdminRole([User::ROLE_SUPER_ADMIN, User::ROLE_EVENT_MANAGER])) {
            return response()->json(['message' => 'This account is not authorized to view staff checkpoints.',
                'code' => 'checkpoint_list_forbidden'], 403, self::HEADERS);
        }
        if ($staff->email_verified_at === null) {
            return response()->json(['message' => 'Activate your administrator account before viewing staff checkpoints.',
                'code' => 'staff_email_unverified'], 403, self::HEADERS);
        }

        $filters = $request->validate([
            'event_id' => ['sometimes', 'integer', 'min:1'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        if (! Schema::hasTable('category_course_checkpoints')) {
            // GET must never migrate, backfill or assign permanent identities.
            return response()->json(['message' => 'The checkpoint list is not configured yet.',
                'code' => 'checkpoint_setup_unavailable'], 503, self::HEADERS);
        }

        // Eager-loading supplies a HasMany relation; whereHas supplies a
        // Builder. Both support these constraints through Eloquent's API.
        $categories = static function ($query) use ($filters): void {
            $query->whereIn('status', ['open', 'closed'])
                ->when(isset($filters['category_id']), fn ($category) => $category->whereKey($filters['category_id']));
        };
        $events = Event::query()
            ->select(['id', 'title', 'event_date'])
            // Legacy events without explicit review approval are not silently
            // promoted. Historical approved revision copies are not live setup.
            ->where('review_status', 'approved')->whereNull('revision_of_id')
            ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
            ->when($staff->managesAssignedEventsOnly(), fn (Builder $query) => $query->where('manager_id', $staff->id))
            ->when(isset($filters['event_id']), fn (Builder $query) => $query->whereKey($filters['event_id']))
            ->whereHas('categories', $categories)
            ->with(['categories' => function ($query) use ($categories): void {
                $categories($query);
                $query->select(['id', 'event_id', 'name', 'course_map'])
                    ->with('activeCourseCheckpoints')->orderBy('name')->orderBy('id');
            }])
            ->orderByDesc('event_date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'data' => $events->getCollection()->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->toDateString(),
                'categories' => $event->categories->map(fn (Category $category) => $this->categoryPayload($category))->values(),
            ])->values(),
            'meta' => ['read_only' => true, 'scan_recording_available' => false,
                'current_page' => $events->currentPage(), 'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(), 'total' => $events->total()],
        ], 200, self::HEADERS);
    }

    private function categoryPayload(Category $category): array
    {
        $source = $category->getRawOriginal('course_map');
        $valid = true;
        (new CategoryCourseMap)->validate('course_map', $source, function () use (&$valid): void {
            $valid = false;
        });
        $status = 'invalid_drawn_route';
        $records = $category->activeCourseCheckpoints;
        if ($valid) {
            $map = CategoryCourseMap::normalize($source);
            $points = $map['checkpoints'] ?? [];
            $matches = $records->count() === count($points);
            foreach ($records as $index => $record) {
                $point = $points[$index] ?? null;
                if (! $point || $record->position !== $index + 1 || $record->label !== $point['label']
                    || round((float) $record->latitude, 6) !== $point['lat']
                    || round((float) $record->longitude, 6) !== $point['lng']) {
                    $matches = false;
                    break;
                }
            }
            $status = ! $matches ? 'checkpoint_list_needs_sync'
                : ($map === null ? 'no_drawn_route' : ($points === [] ? 'no_checkpoints' : 'ready'));
        }

        return ['id' => $category->id, 'name' => $category->name,
            'checkpoint_list_status' => $status,
            'checkpoints' => $status === 'ready' ? $records->map(fn ($record) => [
                'id' => $record->id, 'position' => $record->position, 'label' => $record->label,
            ])->values()->all() : []];
    }
}
