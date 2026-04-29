<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['registrations', 'communityPosts']);
        $completedResults = $user->raceResults()->count();
        $earlyEventCount = $user->registrations()
            ->whereHas('event', fn ($query) => $query->whereTime('start_time', '<=', '05:59:59'))
            ->count();

        return response()->json([
            'data' => [
                $this->achievement('first_event', 'First Event', 'Join your first event', $user->registrations_count >= 1),
                $this->achievement('explorer', 'Explorer', 'Join 3 different events', $user->registrations_count >= 3),
                $this->achievement('consistent_athlete', 'Consistent Athlete', 'Join events 5 times', $user->registrations_count >= 5),
                $this->achievement('early_bird', 'Early Bird', 'Join a 5AM event', $earlyEventCount >= 1),
                $this->achievement('social_athlete', 'Social Athlete', 'Post 5 times in community', $user->community_posts_count >= 5),
                $this->achievement('champion', 'Champion', 'Complete a major event', $completedResults >= 1),
            ],
        ]);
    }

    private function achievement(string $key, string $title, string $description, bool $unlocked): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'status' => $unlocked ? 'unlocked' : 'locked',
            'unlocked' => $unlocked,
        ];
    }
}
