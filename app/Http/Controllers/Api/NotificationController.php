<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->visibleNotifications($user)
            ->latest('sent_at')
            ->latest()
            ->get();

        $readAtByNotificationId = DB::table('notification_reads')
            ->where('user_id', $user->id)
            ->whereIn('push_notification_id', $notifications->pluck('id'))
            ->pluck('read_at', 'push_notification_id');

        $unreadCount = $notifications
            ->reject(fn (PushNotification $notification) => $readAtByNotificationId->has($notification->id))
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'data' => $notifications->map(fn (PushNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'data' => $notification->data,
                'target_user_id' => $notification->target_user_id,
                'created_at' => optional($notification->created_at)?->toISOString(),
                'sent_at' => optional($notification->sent_at)?->toISOString(),
                'is_read' => $readAtByNotificationId->has($notification->id),
                'read_at' => $readAtByNotificationId->get($notification->id)
                    ? \Carbon\Carbon::parse($readAtByNotificationId->get($notification->id))->toISOString()
                    : null,
            ]),
        ]);
    }

    public function markRead(Request $request, PushNotification $notification): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->visibleNotifications($user)->whereKey($notification->id)->exists(), 404);

        DB::table('notification_reads')->updateOrInsert(
            [
                'user_id' => $user->id,
                'push_notification_id' => $notification->id,
            ],
            [
                'read_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();
        $notificationIds = $this->visibleNotifications($user)->pluck('id');

        foreach ($notificationIds as $notificationId) {
            DB::table('notification_reads')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'push_notification_id' => $notificationId,
                ],
                [
                    'read_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        return response()->json([
            'message' => 'Notifications marked as read.',
        ]);
    }

    private function audiencesFor(User $user): array
    {
        $audiences = ['all'];

        if ($user->normalizedRole() === User::ROLE_RUNNER) {
            $audiences[] = 'runners';
        }

        if ($user->registrations()->exists()) {
            $audiences[] = 'participants';
        }

        if ($user->isAdmin()) {
            $audiences[] = 'admins';
        }

        return $audiences;
    }

    private function visibleNotifications(User $user)
    {
        $joinedAt = $user->created_at;

        return PushNotification::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNotNull('sent_at')
                    ->orWhereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->when($joinedAt, function ($query) use ($joinedAt) {
                $query->where(function ($freshQuery) use ($joinedAt) {
                    $freshQuery->where('sent_at', '>=', $joinedAt)
                        ->orWhere(function ($unsentQuery) use ($joinedAt) {
                            $unsentQuery->whereNull('sent_at')
                                ->where(function ($dateQuery) use ($joinedAt) {
                                    $dateQuery->where('scheduled_at', '>=', $joinedAt)
                                        ->orWhere(function ($createdQuery) use ($joinedAt) {
                                            $createdQuery->whereNull('scheduled_at')
                                                ->where('created_at', '>=', $joinedAt);
                                        });
                                });
                        });
                });
            })
            ->where(function ($query) use ($user) {
                $query->where('target_user_id', $user->id)
                    ->orWhere(function ($audienceQuery) use ($user) {
                        $audienceQuery->whereNull('target_user_id')
                            ->whereIn('target_audience', $this->audiencesFor($user));
                    });
            });
    }
}
