<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnnouncementResource;
use App\Http\Resources\Api\TrainingModuleResource;
use App\Models\Announcement;
use App\Models\CommunityPost;
use App\Models\TrainingModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContentController extends Controller
{
    public function announcements(): JsonResponse
    {
        $announcements = Announcement::with('event:id,title')
            ->where('is_published', true)
            ->latest()
            ->get();

        return response()->json([
            'data' => AnnouncementResource::collection($announcements),
        ]);
    }

    public function trainingModules(): JsonResponse
    {
        return response()->json([
            'data' => TrainingModuleResource::collection(
                TrainingModule::where('is_published', true)->latest()->get()
            ),
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
        return response()->json([
            'data' => CommunityPost::with(['user:id,name,avatar_path', 'event:id,title'])
                ->where('is_flagged', false)
                ->latest()
                ->get()
                ->map(fn (CommunityPost $post) => $this->postPayload($post)),
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
        ])->load(['user:id,name,avatar_path', 'event:id,title']);

        return response()->json([
            'message' => 'Post created successfully.',
            'data' => $this->postPayload($post),
        ], 201);
    }

    public function destroyCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete your own posts.',
            ], 403);
        }

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        if ($post->video_path) {
            Storage::disk('public')->delete($post->video_path);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    private function postPayload(CommunityPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
            'image_url' => $post->image_path ? asset('storage/'.$post->image_path) : null,
            'video_url' => $post->video_path ? asset('storage/'.$post->video_path) : null,
            'created_at' => optional($post->created_at)?->toISOString(),
            'event' => $post->event ? [
                'id' => $post->event->id,
                'title' => $post->event->title,
            ] : null,
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar_url' => $post->user->avatar_path ? asset('storage/'.$post->user->avatar_path) : null,
            ],
        ];
    }
}
