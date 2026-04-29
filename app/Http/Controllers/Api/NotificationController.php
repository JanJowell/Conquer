<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $audiences = ['all'];

        if ($user->registrations()->exists()) {
            $audiences[] = 'participants';
        }

        if ($user->isAdmin()) {
            $audiences[] = 'admins';
        }

        $notifications = PushNotification::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNotNull('sent_at')
                    ->orWhereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->whereIn('target_audience', $audiences)
            ->latest('sent_at')
            ->latest()
            ->get();

        return response()->json([
            'data' => $notifications->map(fn (PushNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'created_at' => optional($notification->created_at)?->toISOString(),
                'sent_at' => optional($notification->sent_at)?->toISOString(),
            ]),
        ]);
    }
}
