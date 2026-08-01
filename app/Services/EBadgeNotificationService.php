<?php

namespace App\Services;

use App\Models\IssuedEBadge;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Log;

class EBadgeNotificationService
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging)
    {
    }

    public function notifyIssued(IssuedEBadge $issuedBadge): void
    {
        $issuedBadge->loadMissing(['badge', 'user', 'event']);

        if (! $issuedBadge->user || ! $issuedBadge->badge) {
            return;
        }

        $badgeTitle = $issuedBadge->badge->title;
        $eventTitle = $issuedBadge->event?->title;
        $message = $eventTitle
            ? "You earned the {$badgeTitle} e-badge for {$eventTitle}."
            : "You earned the {$badgeTitle} e-badge.";

        $notification = PushNotification::create([
            'title' => 'New E-Badge Earned',
            'message' => $message,
            'type' => 'achievement',
            'target_audience' => 'runners',
            'target_user_id' => $issuedBadge->user_id,
            'data' => [
                'screen' => 'achievements',
                'issued_e_badge_id' => (string) $issuedBadge->id,
                'e_badge_id' => (string) $issuedBadge->e_badge_id,
                'event_id' => (string) $issuedBadge->event_id,
                'registration_id' => (string) $issuedBadge->registration_id,
            ],
            'is_active' => true,
        ]);

        try {
            $result = $this->messaging->sendNotification($notification, collect([$issuedBadge->user]));

            if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
                $notification->update(['sent_at' => now()]);
            } elseif (! ($result['retry'] ?? false)) {
                $notification->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('E-badge notification could not be delivered immediately.', [
                'issued_e_badge_id' => $issuedBadge->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
