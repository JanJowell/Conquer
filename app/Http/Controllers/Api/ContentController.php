<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnnouncementResource;
use App\Http\Resources\Api\TrainingModuleResource;
use App\Models\Announcement;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\CommunityPostHidden;
use App\Models\CommunityPostReport;
use App\Models\PushNotification;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\FirebaseCloudMessaging;
use App\Services\MobileRecommendationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    private const COMMUNITY_POST_REPORT_HIDE_THRESHOLD = 3;

    public function announcements(): JsonResponse
    {
        $announcements = Announcement::with('event:id,title')
            ->active()
            ->latest('published_at')
            ->get();

        return response()->json([
            'data' => AnnouncementResource::collection($announcements),
        ]);
    }

    public function trainingModules(Request $request): JsonResponse
    {
        $recommendations = app(MobileRecommendationContext::class);
        $interests = $recommendations->requestedInterests($request);

        if ($interests === [] && $request->boolean('recommended')) {
            $interests = $recommendations->recommendedInterests($request);
        }

        $modules = TrainingModule::where('is_published', true)
            ->when($interests !== [], function ($query) use ($interests) {
                $query->where(function ($interestQuery) use ($interests) {
                    $interestQuery->whereIn('interest_type', $interests)
                        ->orWhereNull('interest_type');
                })
                    ->orderByRaw('CASE WHEN interest_type IS NULL THEN 1 ELSE 0 END');
            })
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => TrainingModuleResource::collection($modules->getCollection()),
            'meta' => [
                'current_page' => $modules->currentPage(),
                'last_page' => $modules->lastPage(),
                'per_page' => $modules->perPage(),
                'total' => $modules->total(),
                'recommended' => $request->boolean('recommended') && $interests !== [],
                'matched_interests' => $interests,
            ],
            'links' => [
                'next' => $modules->nextPageUrl(),
                'prev' => $modules->previousPageUrl(),
            ],
        ]);
    }

    public function showTrainingModule(TrainingModule $module): JsonResponse
    {
        abort_unless($module->is_published, 404);

        return response()->json([
            'data' => new TrainingModuleResource($module),
        ]);
    }

    public function communityPosts(): JsonResponse
    {
        $posts = CommunityPost::with([
            'user:id,name,avatar_path',
            'event:id,title',
            'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])
            ->withCount('likes')
            ->where('is_flagged', false)
            ->latest()
            ->paginate(15);

        return response()->json($this->paginatedPostsPayload($posts));
    }

    public function communityFeed(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $posts = CommunityPost::with([
            'user:id,name,avatar_path',
            'event:id,title',
            'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])
            ->withCount('likes')
            ->where('is_flagged', false)
            ->when($userId, fn ($query) => $query->whereDoesntHave(
                'hides',
                fn ($hideQuery) => $hideQuery->where('user_id', $userId)
            ))
            ->latest()
            ->paginate(15);

        return response()->json($this->paginatedPostsPayload($posts, $userId));
    }

    public function showCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->is_flagged, 404);

        $post->load([
            'user:id,name,avatar_path',
            'event:id,title',
            'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])->loadCount('likes');

        return response()->json([
            'data' => $this->postPayload($post, $request->user()->id),
        ]);
    }

    public function storeCommunityPost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'event_id' => ['nullable', 'exists:events,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:20480'],
        ]);

        $post = CommunityPost::create([
            'user_id' => $request->user()->id,
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'image_path' => isset($validated['image']) ? $validated['image']->store('community/images', 'public') : null,
            'video_path' => isset($validated['video']) ? $validated['video']->store('community/videos', 'public') : null,
        ])->load(['user:id,name,avatar_path', 'event:id,title', 'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path')]);

        $post->loadCount('likes');

        return response()->json([
            'message' => 'Post created successfully.',
            'data' => $this->postPayload($post, $request->user()->id),
        ], 201);
    }

    public function storeCommunityPostComment(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->is_flagged, 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment = CommunityPostComment::create([
            'community_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ])->load('user:id,name,avatar_path');

        $this->notifyCommunityPostOwner($post, $request->user(), 'comment');

        return response()->json([
            'message' => 'Comment added successfully.',
            'data' => $this->commentPayload($comment),
        ], 201);
    }

    public function updateCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->is_flagged, 404);

        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You can only edit your own posts.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $post->update([
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
        ]);

        $post->load(['user:id,name,avatar_path', 'event:id,title', 'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path')]);
        $post->loadCount('likes');

        return response()->json([
            'message' => 'Post updated successfully.',
            'data' => $this->postPayload($post, $request->user()->id),
        ]);
    }

    public function hiddenCommunityPosts(Request $request): JsonResponse
    {
        $hides = CommunityPostHidden::with([
            'post.user:id,name,avatar_path',
            'post.event:id,title',
            'post.comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $posts = $hides
            ->map(fn (CommunityPostHidden $hide) => $hide->post)
            ->filter(fn (?CommunityPost $post) => $post && ! $post->trashed())
            ->each(fn (CommunityPost $post) => $post->loadCount('likes'))
            ->map(fn (CommunityPost $post) => $this->postPayload($post, $request->user()->id))
            ->values();

        return response()->json([
            'data' => $posts,
        ]);
    }

    public function hideCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        if ((int) $post->user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'You cannot hide your own post.',
            ], 422);
        }

        CommunityPostHidden::firstOrCreate([
            'user_id' => $request->user()->id,
            'community_post_id' => $post->id,
        ]);

        return response()->json([
            'message' => 'Post hidden from your feed.',
        ]);
    }

    public function unhideCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        CommunityPostHidden::where('user_id', $request->user()->id)
            ->where('community_post_id', $post->id)
            ->delete();

        return response()->json([
            'message' => 'Post restored to your feed.',
        ]);
    }

    public function reportedCommunityPosts(Request $request): JsonResponse
    {
        $reports = CommunityPostReport::with([
            'post.user:id,name,avatar_path',
            'post.event:id,title',
            'post.comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $posts = $reports
            ->filter(fn (CommunityPostReport $report) => $report->post && ! $report->post->trashed())
            ->map(function (CommunityPostReport $report) use ($request) {
                $post = $report->post;
                $post->loadCount('likes');

                return array_merge($this->postPayload($post, $request->user()->id), [
                    'report_reason' => $report->reason,
                    'reported_at' => optional($report->created_at)?->toISOString(),
                ]);
            })
            ->values();

        return response()->json([
            'data' => $posts,
        ]);
    }

    public function reportCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->is_flagged, 404);

        if ($request->user()->email_verified_at === null) {
            return response()->json([
                'message' => 'Only verified users can report community posts.',
            ], 403);
        }

        if ((int) $post->user_id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'You cannot report your own post.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        [$reportCount, $temporarilyHidden] = DB::transaction(function () use ($post, $request, $validated) {
            $lockedPost = CommunityPost::whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedPost->is_flagged, 404);

            CommunityPostReport::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'community_post_id' => $lockedPost->id,
                ],
                [
                    'reason' => $validated['reason'],
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                ]
            );

            $reportCount = $lockedPost->reports()
                ->whereNull('reviewed_at')
                ->whereHas('user', fn ($query) => $query->whereNotNull('email_verified_at'))
                ->count();
            $temporarilyHidden = $reportCount >= self::COMMUNITY_POST_REPORT_HIDE_THRESHOLD;

            $lockedPost->update([
                'is_flagged' => $temporarilyHidden,
                'moderation_note' => $temporarilyHidden
                    ? "Temporarily hidden after {$reportCount} unresolved reports from verified users."
                    : "Received {$reportCount} unresolved report(s) from verified users; awaiting moderator review.",
                'moderated_by' => null,
                'moderated_at' => $temporarilyHidden ? now() : null,
            ]);

            return [$reportCount, $temporarilyHidden];
        }, 3);

        return response()->json([
            'message' => 'Post reported for moderator review.',
            'data' => [
                'report_count' => $reportCount,
                'temporarily_hidden' => $temporarilyHidden,
                'hide_threshold' => self::COMMUNITY_POST_REPORT_HIDE_THRESHOLD,
            ],
        ]);
    }

    public function toggleCommunityPostLike(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->is_flagged, 404);

        $like = $post->likes()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            $post->likes()->create([
                'user_id' => $request->user()->id,
            ]);
            $liked = true;

            $this->notifyCommunityPostOwner($post, $request->user(), 'like');
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function destroyCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete your own posts.',
            ], 403);
        }

        $post->forceFill(['deleted_by_user_id' => $request->user()->id])->save();
        $post->delete();

        return response()->json([
            'message' => 'Post moved to archive. It will be permanently deleted after 30 days.',
        ]);
    }

    public function archivedCommunityPosts(Request $request): JsonResponse
    {
        $posts = CommunityPost::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->whereColumn('deleted_by_user_id', 'user_id')
            ->where('deleted_at', '>', now()->subDays(30))
            ->with([
                'user:id,name,avatar_path',
                'event:id,title',
                'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
            ])
            ->withCount('likes')
            ->latest('deleted_at')
            ->paginate(15);

        return response()->json([
            'data' => $posts->getCollection()
                ->map(fn (CommunityPost $post) => [
                    ...$this->postPayload($post, $request->user()->id),
                    'deleted_at' => $post->deleted_at?->toISOString(),
                    'permanently_deleted_at' => $post->deleted_at?->copy()->addDays(30)->toISOString(),
                ])
                ->values(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
            'links' => [
                'next' => $posts->nextPageUrl(),
                'prev' => $posts->previousPageUrl(),
            ],
        ]);
    }

    public function restoreCommunityPost(Request $request, int $post): JsonResponse
    {
        $archivedPost = CommunityPost::onlyTrashed()->findOrFail($post);

        if ((int) $archivedPost->user_id !== (int) $request->user()->id
            || (int) $archivedPost->deleted_by_user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You can only restore posts that you moved to archive.',
            ], 403);
        }

        if ($archivedPost->deleted_at->lte(now()->subDays(30))) {
            return response()->json([
                'message' => 'This post can no longer be restored.',
            ], 410);
        }

        $archivedPost->restore();
        $archivedPost->forceFill(['deleted_by_user_id' => null])->save();
        $archivedPost->load([
            'user:id,name,avatar_path',
            'event:id,title',
            'comments' => fn ($query) => $query->where('is_flagged', false)->with('user:id,name,avatar_path'),
        ])->loadCount('likes');

        return response()->json([
            'message' => 'Post restored successfully.',
            'data' => $this->postPayload($archivedPost, $request->user()->id),
        ]);
    }

    private function postPayload(CommunityPost $post, ?int $viewerId = null): array
    {
        $post->user?->loadCount('issuedEBadges');

        return [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
            'image_url' => $post->image_path ? asset('storage/'.$post->image_path) : null,
            'video_url' => $post->video_path ? asset('storage/'.$post->video_path) : null,
            'created_at' => optional($post->created_at)?->toISOString(),
            'likes_count' => $post->likes_count ?? $post->likes()->count(),
            'liked_by_me' => $viewerId
                ? $post->likes()->where('user_id', $viewerId)->exists()
                : false,
            'comments' => $post->comments
                ->sortBy('created_at')
                ->map(fn (CommunityPostComment $comment) => $this->commentPayload($comment))
                ->values(),
            'event' => $post->event ? [
                'id' => $post->event->id,
                'title' => $post->event->title,
            ] : null,
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar_url' => $post->user->avatar_path ? asset('storage/'.$post->user->avatar_path) : null,
                'badges_count' => $post->user->issued_e_badges_count ?? 0,
            ],
        ];
    }

    private function paginatedPostsPayload($posts, ?int $viewerId = null): array
    {
        return [
            'data' => $posts->getCollection()
                ->map(fn (CommunityPost $post) => $this->postPayload($post, $viewerId))
                ->values(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
            'links' => [
                'next' => $posts->nextPageUrl(),
                'prev' => $posts->previousPageUrl(),
            ],
        ];
    }

    private function commentPayload(CommunityPostComment $comment): array
    {
        $comment->user?->loadCount('issuedEBadges');

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => optional($comment->created_at)?->toISOString(),
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar_url' => $comment->user->avatar_path ? asset('storage/'.$comment->user->avatar_path) : null,
                'badges_count' => $comment->user->issued_e_badges_count ?? 0,
            ],
        ];
    }

    private function notifyCommunityPostOwner(CommunityPost $post, User $actor, string $action): void
    {
        $post->loadMissing('user:id,name');

        if (! $post->user || (int) $post->user_id === (int) $actor->id) {
            return;
        }

        $notification = PushNotification::create([
            'title' => $action === 'comment' ? 'New comment on your post' : 'New like on your post',
            'message' => $action === 'comment'
                ? "{$actor->name} commented on your community post."
                : "{$actor->name} liked your community post.",
            'type' => 'community',
            'data' => [
                'action' => $action,
                'community_post_id' => $post->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'screen' => 'community_post',
            ],
            'target_audience' => 'runners',
            'target_user_id' => $post->user_id,
            'sent_at' => now(),
            'is_active' => true,
        ]);

        app(FirebaseCloudMessaging::class)->sendNotification($notification, collect([$post->user]));
    }
}
