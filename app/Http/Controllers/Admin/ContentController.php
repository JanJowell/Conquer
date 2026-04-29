<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\TrainingModule;
use App\Models\Checkpoint;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    // Community Posts Management
    public function communityPosts(Request $request)
    {
        $query = CommunityPost::query();

        if ($request->search) {
            $query->where('content', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function ($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%');
                  });
        }

        if ($request->status === 'flagged') {
            $query->where('is_flagged', true);
        } elseif ($request->status === 'deleted') {
            $query->onlyTrashed();
        }

        $posts = $query->with(['user', 'event'])
            ->latest()
            ->paginate(10);

        return view('admin.content.community-posts', compact('posts'));
    }

    public function deleteCommunityPost(CommunityPost $post)
    {
        $post->delete();

        return redirect()->back()
            ->with('success', 'Post deleted successfully.');
    }

    public function restoreCommunityPost($id)
    {
        $post = CommunityPost::withTrashed()->find($id);
        $post->restore();

        return redirect()->back()
            ->with('success', 'Post restored successfully.');
    }

    public function flagCommunityPost(CommunityPost $post)
    {
        $post->update(['is_flagged' => true]);

        return redirect()->back()
            ->with('success', 'Post flagged successfully.');
    }

    public function unflagCommunityPost(CommunityPost $post)
    {
        $post->update(['is_flagged' => false]);

        return redirect()->back()
            ->with('success', 'Post unflagged successfully.');
    }

    // Training Modules Management
    public function trainingModules()
    {
        $modules = TrainingModule::latest()->paginate(10);

        return view('admin.content.training-modules', compact('modules'));
    }

    public function createTrainingModule()
    {
        return view('admin.content.training-modules-create');
    }

    public function storeTrainingModule(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|in:warmup,safety,guideline,program',
            'duration' => 'nullable|integer|min:1',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'is_published' => 'boolean',
        ]);

        TrainingModule::create($validated);

        return redirect()->route('admin.content.training-modules')
            ->with('success', 'Training module created successfully.');
    }

    public function editTrainingModule(TrainingModule $module)
    {
        return view('admin.content.training-modules-edit', compact('module'));
    }

    public function updateTrainingModule(Request $request, TrainingModule $module)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|in:warmup,safety,guideline,program',
            'duration' => 'nullable|integer|min:1',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'is_published' => 'boolean',
        ]);

        $module->update($validated);

        return redirect()->route('admin.content.training-modules')
            ->with('success', 'Training module updated successfully.');
    }

    public function destroyTrainingModule(TrainingModule $module)
    {
        $module->delete();

        return redirect()->route('admin.content.training-modules')
            ->with('success', 'Training module deleted successfully.');
    }

    // Checkpoint Management
    public function checkpoints()
    {
        $user = auth()->user();

        $checkpoints = Checkpoint::with('event')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereHas('event', function ($eventQuery) use ($user) {
                    $eventQuery->where('manager_id', $user->id);
                });
            })
            ->latest()
            ->paginate(10);

        return view('admin.content.checkpoints', compact('checkpoints'));
    }

    public function createCheckpoint()
    {
        $user = auth()->user();
        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get();

        return view('admin.content.checkpoints-create', compact('events'));
    }

    public function storeCheckpoint(Request $request)
    {
        $accessibleEventIds = $this->accessibleEventIds($request);

        $validated = $request->validate([
            'event_id' => ['required', Rule::in($accessibleEventIds)],
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'type' => 'required|in:hydration,medical,checkpoint,finish',
            'description' => 'nullable|string',
            'order' => 'required|integer|min:1',
        ]);

        Checkpoint::create($validated);

        return redirect()->route('admin.content.checkpoints')
            ->with('success', 'Checkpoint created successfully.');
    }

    public function editCheckpoint(Checkpoint $checkpoint)
    {
        abort_unless($this->canAccessCheckpoint($checkpoint), 403);

        $user = auth()->user();
        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get();

        return view('admin.content.checkpoints-edit', compact('checkpoint', 'events'));
    }

    public function updateCheckpoint(Request $request, Checkpoint $checkpoint)
    {
        abort_unless($this->canAccessCheckpoint($checkpoint), 403);
        $accessibleEventIds = $this->accessibleEventIds($request);

        $validated = $request->validate([
            'event_id' => ['required', Rule::in($accessibleEventIds)],
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'type' => 'required|in:hydration,medical,checkpoint,finish',
            'description' => 'nullable|string',
            'order' => 'required|integer|min:1',
        ]);

        $checkpoint->update($validated);

        return redirect()->route('admin.content.checkpoints')
            ->with('success', 'Checkpoint updated successfully.');
    }

    public function destroyCheckpoint(Checkpoint $checkpoint)
    {
        abort_unless($this->canAccessCheckpoint($checkpoint), 403);

        $checkpoint->delete();

        return redirect()->route('admin.content.checkpoints')
            ->with('success', 'Checkpoint deleted successfully.');
    }

    private function accessibleEventIds(Request $request): array
    {
        $user = $request->user();

        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function canAccessCheckpoint(Checkpoint $checkpoint): bool
    {
        return $checkpoint->event && auth()->user()->canManageEvent($checkpoint->event);
    }
}
