<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleEventIds = $user->managedEventIds();

        $categories = Category::with('event')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title']);

        return view('admin.categories.index', compact('categories', 'events'));
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

        return view('admin.categories.create', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accessibleEventIds = $this->accessibleEventIds($request);

        $validated = $request->validate([
            'event_id' => ['required', Rule::in($accessibleEventIds)],
            'name' => ['required', 'string', 'max:255'],
            'distance_km' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'slot_limit' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:open,closed,draft'],
        ]);

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless($this->canAccessCategory($category), 403);

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

    private function accessibleEventIds(Request $request): array
    {
        $user = $request->user();

        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function canAccessCategory(Category $category): bool
    {
        $event = $category->event;

        return $event && auth()->user()->canManageEvent($event);
    }
}
