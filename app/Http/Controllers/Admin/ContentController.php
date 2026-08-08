<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\Event;
use App\Models\TrainingModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    // Community Posts Management
    public function pendingReview()
    {
        $flaggedPosts = CommunityPost::with(['user', 'event', 'moderator'])
            ->whereHas('reports', fn ($query) => $query
                ->whereNull('reviewed_at')
                ->whereHas('user', fn ($userQuery) => $userQuery->whereNotNull('email_verified_at')))
            ->withCount(['reports as pending_reports_count' => fn ($query) => $query
                ->whereNull('reviewed_at')
                ->whereHas('user', fn ($userQuery) => $userQuery->whereNotNull('email_verified_at'))])
            ->withMax(['reports as latest_reported_at' => fn ($query) => $query->whereNull('reviewed_at')], 'updated_at')
            ->orderByDesc('latest_reported_at')
            ->take(10)
            ->get();

        $deletedPosts = CommunityPost::onlyTrashed()
            ->with(['user', 'event', 'moderator'])
            ->latest('deleted_at')
            ->take(10)
            ->get();

        $flaggedComments = CommunityPostComment::with(['user', 'post.event', 'moderator'])
            ->where('is_flagged', true)
            ->latest('moderated_at')
            ->latest()
            ->take(10)
            ->get();

        $deletedComments = CommunityPostComment::onlyTrashed()
            ->with(['user', 'post.event', 'moderator'])
            ->latest('deleted_at')
            ->take(10)
            ->get();

        $trainingDrafts = TrainingModule::where('is_published', false)
            ->latest()
            ->take(10)
            ->get();

        return view('admin.content.pending-review', compact(
            'flaggedPosts',
            'deletedPosts',
            'flaggedComments',
            'deletedComments',
            'trainingDrafts'
        ));
    }

    public function communityPosts(Request $request)
    {
        $query = CommunityPost::query();

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('content', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->status === 'flagged') {
            $query->where('is_flagged', true);
        } elseif ($request->status === 'deleted') {
            $query->onlyTrashed();
        }

        $posts = $query->with(['user', 'event'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.content.community-posts', compact('posts'));
    }

    public function showCommunityPost($id)
    {
        $post = CommunityPost::withTrashed()
            ->with([
                'user',
                'event',
                'comments' => fn ($query) => $query->withTrashed()->with(['user', 'moderator']),
                'moderator',
                'reports' => fn ($query) => $query->with(['user', 'reviewer'])->latest('updated_at'),
            ])
            ->withCount(['likes', 'comments', 'reports'])
            ->findOrFail($id);

        return view('admin.content.community-posts-show', compact('post'));
    }

    public function deleteCommunityPost(Request $request, CommunityPost $post)
    {
        DB::transaction(function () use ($request, $post) {
            $post->update([
                ...$this->moderationData($request),
                'deleted_by_user_id' => $request->user()->id,
            ]);
            $this->resolvePendingPostReports($post, $request->user()->id);
            $post->delete();
        });

        return redirect()->back()
            ->with('success', 'Post deleted successfully.');
    }

    public function restoreCommunityPost(Request $request, $id)
    {
        $post = CommunityPost::withTrashed()->findOrFail($id);
        $post->restore();
        $post->update([
            ...$this->moderationData($request),
            'deleted_by_user_id' => null,
        ]);

        return redirect()->back()
            ->with('success', 'Post restored successfully.');
    }

    public function flagCommunityPost(Request $request, CommunityPost $post)
    {
        DB::transaction(function () use ($request, $post) {
            $post->update([
                'is_flagged' => true,
                ...$this->moderationData($request),
            ]);
            $this->resolvePendingPostReports($post, $request->user()->id);
        });

        return redirect()->back()
            ->with('success', 'Post flagged successfully.');
    }

    public function unflagCommunityPost(Request $request, CommunityPost $post)
    {
        DB::transaction(function () use ($request, $post) {
            $post->update([
                'is_flagged' => false,
                ...$this->moderationData($request),
            ]);
            $this->resolvePendingPostReports($post, $request->user()->id);
        });

        return redirect()->back()
            ->with('success', 'Post unflagged successfully.');
    }

    public function flagCommunityComment(Request $request, CommunityPostComment $comment)
    {
        $comment->update([
            'is_flagged' => true,
            ...$this->moderationData($request),
        ]);

        return redirect()->back()
            ->with('success', 'Comment flagged successfully.');
    }

    public function unflagCommunityComment(Request $request, CommunityPostComment $comment)
    {
        $comment->update([
            'is_flagged' => false,
            ...$this->moderationData($request),
        ]);

        return redirect()->back()
            ->with('success', 'Comment unflagged successfully.');
    }

    public function deleteCommunityComment(Request $request, CommunityPostComment $comment)
    {
        $comment->update($this->moderationData($request));
        $comment->delete();

        return redirect()->back()
            ->with('success', 'Comment deleted successfully.');
    }

    public function restoreCommunityComment(Request $request, $id)
    {
        $comment = CommunityPostComment::withTrashed()->findOrFail($id);
        $comment->restore();
        $comment->update($this->moderationData($request));

        return redirect()->back()
            ->with('success', 'Comment restored successfully.');
    }

    // Training Modules Management
    public function trainingModules(Request $request)
    {
        $interestTypes = $this->trainingInterestTypes();

        $modules = TrainingModule::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('interest_type', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('interest_type'), function ($query) use ($request) {
                if ($request->input('interest_type') === 'general') {
                    $query->whereNull('interest_type');
                } else {
                    $query->where('interest_type', $request->input('interest_type'));
                }
            })
            ->when(in_array($request->input('status'), ['published', 'draft'], true), function ($query) use ($request) {
                $query->where('is_published', $request->input('status') === 'published');
            })
            ->when(in_array($request->input('type'), ['warmup', 'safety', 'guideline', 'program'], true), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when(in_array($request->input('difficulty'), ['beginner', 'intermediate', 'advanced'], true), function ($query) use ($request) {
                $query->where('difficulty_level', $request->input('difficulty'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.content.training-modules', compact('interestTypes', 'modules'));
    }

    public function createTrainingModule()
    {
        $interestTypes = $this->trainingInterestTypes();

        return view('admin.content.training-modules-create', compact('interestTypes'));
    }

    public function storeTrainingModule(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|in:warmup,safety,guideline,program',
            'interest_type' => ['nullable', Rule::in($this->trainingInterestTypes())],
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
        $interestTypes = $this->trainingInterestTypes();

        return view('admin.content.training-modules-edit', compact('interestTypes', 'module'));
    }

    public function showTrainingModule(TrainingModule $module)
    {
        return view('admin.content.training-modules-show', compact('module'));
    }

    public function updateTrainingModule(Request $request, TrainingModule $module)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|in:warmup,safety,guideline,program',
            'interest_type' => ['nullable', Rule::in($this->trainingInterestTypes())],
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
    public function checkpoints(Request $request)
    {
        $user = auth()->user();
        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title', 'event_date']);

        $checkpoints = Checkpoint::with('event')
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->whereHas('event', function ($eventQuery) use ($user) {
                    $eventQuery->where('manager_id', $user->id);
                });
            })
            ->when($request->filled('event_id'), function ($query) use ($request, $events) {
                $eventId = $request->integer('event_id');

                if ($events->pluck('id')->contains($eventId)) {
                    $query->where('event_id', $eventId);
                }
            })
            ->when(in_array($request->input('type'), ['hydration', 'medical', 'checkpoint', 'finish'], true), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('event_id')
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.content.checkpoints', compact('checkpoints', 'events'));
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
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
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
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
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

    private function trainingInterestTypes(): array
    {
        return config('conquer.event_interest_types', []);
    }

    private function canAccessCheckpoint(Checkpoint $checkpoint): bool
    {
        return $checkpoint->event && auth()->user()->canManageEvent($checkpoint->event);
    }

    private function moderationData(Request $request): array
    {
        $validated = $request->validate([
            'moderation_note' => ['required', 'string', 'max:1000'],
        ]);

        return [
            'moderation_note' => $validated['moderation_note'],
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ];
    }

    private function resolvePendingPostReports(CommunityPost $post, int $reviewerId): void
    {
        $post->reports()
            ->whereNull('reviewed_at')
            ->update([
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
            ]);
    }
}
