<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EBadge;
use App\Models\User;
use App\Services\EBadgeAutoIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()
            ->load(['issuedEBadges.badge.category', 'issuedEBadges.event']);
        $issuedBadges = $user->issuedEBadges
            ->sortByDesc('issued_at')
            ->values();
        $issuedByBadgeId = $issuedBadges
            ->filter(fn ($issuedBadge) => $issuedBadge->badge)
            ->groupBy('e_badge_id');
        $badges = EBadge::query()
            ->with(['event', 'category'])
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $badges->map(function (EBadge $badge) use ($issuedByBadgeId) {
                $issuedRecords = $issuedByBadgeId->get($badge->id, collect());
                $latestIssued = $issuedRecords->sortByDesc('issued_at')->first();

                return [
                    'id' => $badge->id,
                    'title' => $badge->title,
                    'description' => $badge->description,
                    'criteria' => $this->badgeCriteriaLabel($badge),
                    'auto_issue_rule' => $badge->auto_issue_rule,
                    'auto_issue_rule_label' => EBadgeAutoIssuer::rules()[$badge->auto_issue_rule] ?? null,
                    'image_url' => $badge->image_path ? asset('storage/'.$badge->image_path) : null,
                    'event' => $badge->event ? [
                        'id' => $badge->event->id,
                        'title' => $badge->event->title,
                    ] : null,
                    'category' => $badge->category ? [
                        'id' => $badge->category->id,
                        'name' => $badge->category->name,
                    ] : null,
                    'status' => $issuedRecords->isNotEmpty() ? 'unlocked' : 'locked',
                    'locked' => $issuedRecords->isEmpty(),
                    'earned_count' => $issuedRecords->count(),
                    'issued_badge_id' => $latestIssued?->id,
                    'issued_at' => $latestIssued?->issued_at?->toISOString(),
                    'notes' => $latestIssued?->notes,
                ];
            })->values(),
            'issued_badges' => $issuedBadges->map(fn ($issuedBadge) => [
                'id' => $issuedBadge->id,
                'badge_id' => $issuedBadge->badge?->id,
                'title' => $issuedBadge->badge?->title,
                'description' => $issuedBadge->badge?->description,
                'criteria' => $issuedBadge->badge ? $this->badgeCriteriaLabel($issuedBadge->badge) : null,
                'auto_issue_rule' => $issuedBadge->badge?->auto_issue_rule,
                'auto_issue_rule_label' => $issuedBadge->badge
                    ? (EBadgeAutoIssuer::rules()[$issuedBadge->badge->auto_issue_rule] ?? null)
                    : null,
                'image_url' => $issuedBadge->badge?->image_path ? asset('storage/'.$issuedBadge->badge->image_path) : null,
                'event' => $issuedBadge->event ? [
                    'id' => $issuedBadge->event->id,
                    'title' => $issuedBadge->event->title,
                ] : null,
                'category' => $issuedBadge->badge?->category ? [
                    'id' => $issuedBadge->badge->category->id,
                    'name' => $issuedBadge->badge->category->name,
                ] : null,
                'notes' => $issuedBadge->notes,
                'issued_at' => $issuedBadge->issued_at?->toISOString(),
            ]),
        ]);
    }

    public function leaderboard(): JsonResponse
    {
        $users = User::query()
            ->whereIn('role', [User::ROLE_RUNNER, 'user'])
            ->whereNotNull('email_verified_at')
            ->withCount(['registrations', 'communityPosts', 'raceResults', 'issuedEBadges'])
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
                    'badges_count' => $user->issued_e_badges_count,
                    'registrations_count' => $user->registrations_count,
                    'results_count' => $user->race_results_count,
                    'posts_count' => $user->community_posts_count,
                ];
            })
            ->sort(function (array $first, array $second) {
                return [$second['badges_count'], $second['results_count'], $second['registrations_count'], $second['posts_count']]
                    <=> [$first['badges_count'], $first['results_count'], $first['registrations_count'], $first['posts_count']];
            })
            ->values()
            ->take(20)
            ->map(function (array $user, int $index) {
                return [
                    'rank' => $index + 1,
                    ...$user,
                ];
            });

        return response()->json([
            'data' => $users,
        ]);
    }

    private function badgeCriteriaLabel(EBadge $badge): ?string
    {
        return $badge->criteria ?: (EBadgeAutoIssuer::rules()[$badge->auto_issue_rule] ?? null);
    }
}
