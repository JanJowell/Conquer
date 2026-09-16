<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Log;

class CertificateNotificationService
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging) {}

    public function notifyIssued(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'event']);

        if (! $certificate->user) {
            return;
        }

        $eventTitle = $certificate->event?->title;
        $message = $eventTitle
            ? "Your E-Certificate for {$eventTitle} is ready."
            : 'Your event E-Certificate is ready.';

        try {
            $notification = PushNotification::create([
                'title' => 'E-Certificate Available',
                'message' => $message,
                'type' => 'achievement',
                'target_audience' => 'runners',
                'target_user_id' => $certificate->user_id,
                'data' => [
                    'screen' => 'certificates',
                    'certificate_id' => (string) $certificate->id,
                    'registration_id' => (string) $certificate->registration_id,
                    'event_id' => (string) $certificate->event_id,
                    'verification_url' => route('certificates.verify', $certificate->verification_token),
                    'download_url' => route('certificates.download', $certificate->verification_token),
                ],
                'is_active' => true,
            ]);

            $result = $this->messaging->sendNotification($notification, collect([$certificate->user]));

            if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
                $notification->update(['sent_at' => now()]);
            } elseif (! ($result['retry'] ?? false)) {
                $notification->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('E-Certificate notification could not be created or delivered immediately.', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
