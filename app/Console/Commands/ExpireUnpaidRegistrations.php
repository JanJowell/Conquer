<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PushNotification;
use App\Models\Registration;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidRegistrations extends Command
{
    protected $signature = 'payments:expire-unpaid {--dry-run : Show matching registrations without changing them} {--no-notify : Do not notify runners}';

    protected $description = 'Reject pending paid registrations that missed the registration payment deadline.';

    public function handle(FirebaseCloudMessaging $messaging): int
    {
        $query = $this->expiredRegistrationQuery();
        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Found {$total} unpaid registration(s) eligible for expiry.");

            (clone $query)->limit(20)->get()->each(function (Registration $registration) {
                $this->line(sprintf(
                    '#%d | %s | %s | %s',
                    $registration->id,
                    $registration->user?->email ?: 'No email',
                    $registration->event?->title ?: 'No event',
                    $registration->payment_status
                ));
            });

            return self::SUCCESS;
        }

        $expired = 0;

        $query->chunkById(100, function ($registrations) use (&$expired, $messaging) {
            foreach ($registrations as $registration) {
                DB::transaction(function () use ($registration) {
                    $registration->update([
                        'status' => 'rejected',
                        'rejection_reason' => 'Payment was not completed before the registration deadline.',
                        'payment_status' => Payment::STATUS_EXPIRED,
                        'paid_at' => null,
                    ]);

                    Payment::create([
                        'registration_id' => $registration->id,
                        'user_id' => $registration->user_id,
                        'event_id' => $registration->event_id,
                        'category_id' => $registration->category_id,
                        'provider' => 'system',
                        'provider_reference' => null,
                        'amount_cents' => $registration->payment_amount_cents ?? 0,
                        'currency' => $registration->payment_currency ?? 'PHP',
                        'status' => Payment::STATUS_EXPIRED,
                        'payload' => [
                            'source' => 'payments_expire_unpaid_command',
                            'reason' => 'Registration deadline passed before payment was completed.',
                        ],
                    ]);
                });

                $expired++;

                if (! $this->option('no-notify')) {
                    $this->notifyRunner($registration->fresh(['user', 'event']), $messaging);
                }
            }
        });

        $this->info("Expired {$expired} unpaid registration(s).");

        return self::SUCCESS;
    }

    private function expiredRegistrationQuery()
    {
        return Registration::query()
            ->with(['user', 'event', 'category'])
            ->where('status', 'pending')
            ->where('payment_required', true)
            ->whereIn('payment_status', [
                'unpaid',
                Payment::STATUS_PENDING,
                Payment::STATUS_FAILED,
            ])
            ->whereHas('event', function ($query) {
                $query->whereNotNull('registration_deadline')
                    ->whereDate('registration_deadline', '<', today());
            })
            ->orderBy('id');
    }

    private function notifyRunner(Registration $registration, FirebaseCloudMessaging $messaging): void
    {
        if (! $registration->user) {
            return;
        }

        $eventTitle = $registration->event?->title ?: 'your event';

        $notification = PushNotification::create([
            'title' => 'Registration Payment Expired',
            'message' => "Your registration for {$eventTitle} was rejected because payment was not completed before the deadline.",
            'type' => 'payment',
            'target_audience' => 'runners',
            'target_user_id' => $registration->user_id,
            'data' => [
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
                'payment_status' => Payment::STATUS_EXPIRED,
                'registration_status' => 'rejected',
                'screen' => 'payment',
            ],
            'is_active' => true,
        ]);

        try {
            $result = $messaging->sendNotification($notification, collect([$registration->user]));

            if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
                $notification->update(['sent_at' => now()]);
            } elseif (! ($result['retry'] ?? false)) {
                $notification->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('Payment expiry notification could not be delivered immediately.', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
