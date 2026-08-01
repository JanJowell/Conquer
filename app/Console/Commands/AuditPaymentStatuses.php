<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AuditPaymentStatuses extends Command
{
    protected $signature = 'payments:audit-statuses {--fix : Fix safe registration/payment status mismatches}';

    protected $description = 'Audit registration and payment status consistency.';

    public function handle(): int
    {
        $mismatches = $this->mismatches();
        $total = $mismatches->flatten(1)->count();

        if ($total === 0) {
            $this->info('No payment status mismatches found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$total} payment status mismatch(es).");

        foreach ($mismatches as $type => $registrations) {
            $this->newLine();
            $this->line(str($type)->replace('_', ' ')->title().": {$registrations->count()}");

            $registrations->take(20)->each(function (Registration $registration) {
                $this->line(sprintf(
                    '#%d | reg:%s | pay:%s | bib:%s | %s | %s',
                    $registration->id,
                    $registration->status,
                    $registration->payment_status,
                    $registration->bib_number ?: 'none',
                    $registration->user?->email ?: 'No email',
                    $registration->event?->title ?: 'No event'
                ));
            });
        }

        if (! $this->option('fix')) {
            $this->info('Run with --fix to repair safe mismatches.');

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach (['paid_pending', 'waived_pending', 'expired_not_rejected'] as $safeType) {
            foreach ($mismatches->get($safeType, collect()) as $registration) {
                try {
                    DB::transaction(function () use ($registration, $safeType) {
                        $updates = match ($safeType) {
                            'paid_pending', 'waived_pending' => [
                                'status' => 'approved',
                                'rejection_reason' => null,
                            ],
                            'expired_not_rejected' => [
                                'status' => 'rejected',
                                'rejection_reason' => 'Payment was not completed before the registration deadline.',
                                'paid_at' => null,
                            ],
                        };

                        if (in_array($safeType, ['paid_pending', 'waived_pending'], true)
                            && blank($registration->bib_number)) {
                            $updates['bib_number'] = $this->nextBibNumberForEvent($registration->event_id);
                        }

                        if ($safeType === 'waived_pending') {
                            $updates['payment_required'] = false;
                        }

                        $registration->update($updates);
                    });

                    $fixed++;
                } catch (QueryException) {
                    $this->error("Could not fix registration #{$registration->id}; a bib number may have been assigned concurrently.");
                }
            }
        }

        $this->info("Fixed {$fixed} safe mismatch(es). Risky mismatches were report-only.");

        return self::SUCCESS;
    }

    private function mismatches()
    {
        $registrations = Registration::query()
            ->with(['user', 'event'])
            ->where(function ($query) {
                $query
                    ->where(function ($inner) {
                        $inner->where('status', 'pending')
                            ->whereIn('payment_status', [Payment::STATUS_PAID, Payment::STATUS_WAIVED]);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('payment_status', Payment::STATUS_EXPIRED)
                            ->where('status', '!=', 'rejected');
                    })
                    ->orWhere(function ($inner) {
                        $inner->whereIn('status', ['approved', 'checked_in', 'completed'])
                            ->where('payment_required', true)
                            ->whereNotIn('payment_status', [Payment::STATUS_PAID, Payment::STATUS_WAIVED]);
                    })
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'rejected')
                            ->whereIn('payment_status', [Payment::STATUS_SUBMITTED, Payment::STATUS_PENDING, 'unpaid']);
                    });
            })
            ->orderBy('id')
            ->get();

        return collect([
            'paid_pending' => $registrations->filter(fn ($registration) => $registration->status === 'pending'
                && $registration->payment_status === Payment::STATUS_PAID)->values(),
            'waived_pending' => $registrations->filter(fn ($registration) => $registration->status === 'pending'
                && $registration->payment_status === Payment::STATUS_WAIVED)->values(),
            'expired_not_rejected' => $registrations->filter(fn ($registration) => $registration->payment_status === Payment::STATUS_EXPIRED
                && $registration->status !== 'rejected')->values(),
            'approved_without_completed_payment' => $registrations->filter(fn ($registration) => in_array($registration->status, ['approved', 'checked_in', 'completed'], true)
                && $registration->payment_required
                && ! in_array($registration->payment_status, [Payment::STATUS_PAID, Payment::STATUS_WAIVED], true))->values(),
            'rejected_with_active_payment' => $registrations->filter(fn ($registration) => $registration->status === 'rejected'
                && in_array($registration->payment_status, [Payment::STATUS_SUBMITTED, Payment::STATUS_PENDING, 'unpaid'], true))->values(),
        ])->filter(fn ($registrations) => $registrations->isNotEmpty());
    }

    private function nextBibNumberForEvent(int $eventId): string
    {
        $highestBib = Registration::query()
            ->where('event_id', $eventId)
            ->whereNotNull('bib_number')
            ->lockForUpdate()
            ->pluck('bib_number')
            ->map(fn ($bibNumber) => trim((string) $bibNumber))
            ->filter(fn ($bibNumber) => ctype_digit($bibNumber))
            ->map(fn ($bibNumber) => (int) $bibNumber)
            ->max() ?? 0;

        return str_pad((string) ($highestBib + 1), 3, '0', STR_PAD_LEFT);
    }
}
