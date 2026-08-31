<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Registration;
use Illuminate\Support\Str;

class CertificateIssuer
{
    public function __construct(private readonly CertificateNotificationService $notifications) {}

    public function isEligible(Registration $registration): bool
    {
        $registration->loadMissing(['raceResult', 'feedback']);

        return $registration->status === 'completed'
            && $registration->raceResult !== null
            && $registration->feedback !== null;
    }

    public function syncForRegistration(
        Registration $registration,
        ?int $issuedBy = null,
        bool $notify = true
    ): ?Certificate {
        $registration->loadMissing(['event', 'raceResult', 'feedback', 'certificate']);
        $certificate = $registration->certificate;

        if (! $this->isEligible($registration)) {
            if ($certificate?->isValid()) {
                $certificate->update([
                    'revoked_at' => now(),
                    'revoked_by' => null,
                    'revocation_reason' => 'Certificate eligibility is no longer satisfied.',
                ]);
            }

            return $certificate?->refresh();
        }

        if ($certificate) {
            $certificate->update($this->snapshot($registration));

            if ($certificate->revoked_by === null
                && $certificate->revocation_reason === 'Certificate eligibility is no longer satisfied.') {
                $certificate->update([
                    'revoked_at' => null,
                    'revocation_reason' => null,
                ]);
            }

            return $certificate->refresh();
        }

        $year = $registration->event?->event_date?->format('Y') ?? now()->format('Y');

        $certificate = Certificate::firstOrCreate(
            ['registration_id' => $registration->id],
            [
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'category_id' => $registration->category_id,
                'certificate_number' => sprintf('RCT-%s-%08d', $year, $registration->id),
                'verification_token' => (string) Str::uuid(),
                ...$this->snapshot($registration),
                'issued_by' => $issuedBy,
                'issued_at' => now(),
            ]
        );

        if ($notify && $certificate->wasRecentlyCreated) {
            $this->notifications->notifyIssued($certificate);
        }

        return $certificate;
    }

    public function syncForEvent(int $eventId, bool $notify = true): int
    {
        $count = 0;

        Registration::query()
            ->where('event_id', $eventId)
            ->where(function ($query) {
                $query->where('status', 'completed')->orWhereHas('certificate');
            })
            ->with(['event', 'raceResult', 'feedback', 'certificate'])
            ->chunkById(100, function ($registrations) use (&$count, $notify) {
                foreach ($registrations as $registration) {
                    $before = $registration->certificate?->updated_at;
                    $certificate = $this->syncForRegistration($registration, notify: $notify);

                    if ($certificate && (! $before || ! $certificate->updated_at->equalTo($before))) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function syncAll(bool $notify = false): int
    {
        $count = 0;

        Registration::query()
            ->where(function ($query) {
                $query->where('status', 'completed')->orWhereHas('certificate');
            })
            ->with(['event', 'raceResult', 'feedback', 'certificate'])
            ->chunkById(100, function ($registrations) use (&$count, $notify) {
                foreach ($registrations as $registration) {
                    if ($this->syncForRegistration($registration, notify: $notify)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function snapshot(Registration $registration): array
    {
        $registration->loadMissing(['user', 'event', 'category', 'raceResult']);

        return [
            'participant_name' => $registration->user?->name ?: 'Participant',
            'event_title' => $registration->event?->title ?: 'Event',
            'category_name' => $registration->category?->name ?: 'Category',
            'distance_km' => $registration->category?->distance_km,
            'event_date' => $registration->event?->event_date,
            'venue' => $registration->event?->venue,
            'finish_time' => $registration->raceResult?->finish_time,
            'rank_overall' => $registration->raceResult?->rank_overall,
            'rank_category' => $registration->raceResult?->rank_category,
        ];
    }
}
