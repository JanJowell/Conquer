<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PushNotification;
use App\Models\Registration;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $managedEventIds = $user->managedEventIds();

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title']);

        $registrations = $this->filteredRegistrationQuery($request)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summaryBase = Registration::query()
            ->where(function ($query) {
                $query->where('payment_required', true)
                    ->orWhere('payment_amount_cents', '>', 0)
                    ->orWhereHas('payments');
            })
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            });

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'unpaid' => (clone $summaryBase)->whereIn('payment_status', ['unpaid', 'pending'])->count(),
            'submitted' => (clone $summaryBase)->where('payment_status', Payment::STATUS_SUBMITTED)->count(),
            'paid' => (clone $summaryBase)->where('payment_status', 'paid')->count(),
            'waived' => (clone $summaryBase)->where('payment_status', 'waived')->count(),
            'collected_cents' => (clone $summaryBase)->where('payment_status', 'paid')->sum('payment_amount_cents'),
        ];

        $paymentStatuses = $this->paymentStatuses();
        $providers = Payment::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->select('provider')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider');

        return view('admin.payments.index', compact('registrations', 'events', 'summary', 'paymentStatuses', 'providers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'payments-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Registration ID',
                'Participant Name',
                'Participant Email',
                'Event',
                'Category',
                'Bib Number',
                'Registration Status',
                'Payment Status',
                'Payment Required',
                'Amount',
                'Currency',
                'Latest Provider',
                'Latest Reference',
                'Latest Payment Status',
                'Latest Notes',
                'Submitted At',
                'Paid At',
                'Registered At',
            ]);

            $this->filteredRegistrationQuery($request)
                ->latest()
                ->chunk(200, function ($registrations) use ($handle) {
                    foreach ($registrations as $registration) {
                        $payment = $registration->latestPayment;

                        fputcsv($handle, [
                            $registration->id,
                            $registration->user?->name,
                            $registration->user?->email,
                            $registration->event?->title,
                            $registration->category?->name,
                            $registration->bib_number,
                            $registration->status,
                            $registration->payment_status,
                            $registration->payment_required ? 'yes' : 'no',
                            number_format(($registration->payment_amount_cents ?? 0) / 100, 2, '.', ''),
                            $registration->payment_currency ?? 'PHP',
                            $payment?->provider,
                            $payment?->provider_reference,
                            $payment?->status,
                            $payment?->payload['notes'] ?? null,
                            optional($payment?->submitted_at)?->format('Y-m-d H:i:s'),
                            optional($registration->paid_at)?->format('Y-m-d H:i:s'),
                            optional($registration->registered_at ?? $registration->created_at)?->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function update(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($this->canAccessRegistration($registration, $request), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['paid', 'waived', 'failed', 'pending', 'submitted', 'refunded', 'cancelled'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'required_if:action,refunded,cancelled', 'string', 'max:1000'],
        ]);

        if ($actionError = $this->paymentActionError($registration, $validated['action'])) {
            return back()->withInput()->with('error', $actionError);
        }

        try {
            $result = DB::transaction(function () use ($request, $registration, $validated) {
                $status = $validated['action'];
                $now = now();
                $amountCents = (int) ($registration->payment_amount_cents ?? 0);
                $currency = $registration->payment_currency ?: 'PHP';
                $provider = trim($validated['provider'] ?? '') ?: 'manual';
                $autoApproved = false;
                $assignedBibNumber = null;

                $registrationUpdates = [
                    'payment_status' => $status,
                    'paid_at' => $status === 'paid' ? $now : null,
                    'payment_required' => $status === 'waived' ? false : $amountCents > 0,
                ];

                if ($status === Payment::STATUS_PAID && $registration->status === 'pending') {
                    $registrationUpdates['status'] = 'approved';
                    $registrationUpdates['rejection_reason'] = null;
                    $autoApproved = true;

                    if (blank($registration->bib_number)) {
                        $assignedBibNumber = $this->nextBibNumberForEvent($registration->event_id);
                        $registrationUpdates['bib_number'] = $assignedBibNumber;
                    }
                }

                if (in_array($status, [Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED], true)) {
                    $registrationUpdates['status'] = 'rejected';
                    $registrationUpdates['rejection_reason'] = $status === Payment::STATUS_REFUNDED
                        ? 'Payment was refunded.'
                        : 'Payment was cancelled.';
                    $registrationUpdates['bib_number'] = null;
                    $registrationUpdates['paid_at'] = null;
                    $registrationUpdates['payment_required'] = $amountCents > 0;
                }

                $registration->update($registrationUpdates);

                Payment::create([
                    'registration_id' => $registration->id,
                    'user_id' => $registration->user_id,
                    'event_id' => $registration->event_id,
                    'category_id' => $registration->category_id,
                    'provider' => match ($status) {
                        Payment::STATUS_WAIVED => 'manual_waiver',
                        Payment::STATUS_REFUNDED => 'manual_refund',
                        Payment::STATUS_CANCELLED => 'manual_cancel',
                        default => $provider,
                    },
                    'provider_reference' => $validated['provider_reference'] ?? null,
                    'amount_cents' => $status === Payment::STATUS_WAIVED ? 0 : $amountCents,
                    'currency' => $currency,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? $now : null,
                    'payload' => [
                        'action' => $status,
                        'notes' => $validated['notes'] ?? null,
                        'original_amount_cents' => $amountCents,
                        'auto_approved_registration' => $autoApproved,
                        'assigned_bib_number' => $assignedBibNumber,
                        'updated_by' => $request->user()->id,
                        'source' => 'admin_payments_page',
                    ],
                ]);

                return [
                    'auto_approved' => $autoApproved,
                    'assigned_bib_number' => $assignedBibNumber,
                ];
            });
        } catch (QueryException) {
            return back()
                ->withInput()
                ->with('error', 'A bib number was just assigned. Please save again to get the next available number.');
        }

        $this->notifyRunnerAboutPaymentStatus(
            $registration->fresh(['user', 'event', 'category']),
            $validated['action'],
            $result['auto_approved']
        );

        return back()->with(
            'success',
            $result['auto_approved']
                ? 'Payment marked paid and participant approved successfully.'
                : 'Payment status updated successfully.'
        );
    }

    private function paymentStatuses(): array
    {
        return [
            'unpaid' => 'Unpaid',
            'pending' => 'Pending',
            'submitted' => 'Submitted Proof',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'expired' => 'Expired',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
            'waived' => 'Waived',
        ];
    }

    private function paymentActionError(Registration $registration, string $action): ?string
    {
        if (in_array($registration->status, ['checked_in', 'completed'], true)
            && in_array($action, [
                Payment::STATUS_WAIVED,
                Payment::STATUS_FAILED,
                Payment::STATUS_PENDING,
                Payment::STATUS_SUBMITTED,
                Payment::STATUS_CANCELLED,
            ], true)) {
            return 'Checked-in or completed participants cannot be moved back to an unpaid, failed, submitted, waived, or cancelled payment state.';
        }

        if ($action === Payment::STATUS_REFUNDED && $registration->payment_status !== Payment::STATUS_PAID) {
            return 'Only paid registrations can be marked as refunded.';
        }

        if ($action === Payment::STATUS_CANCELLED && $registration->payment_status === Payment::STATUS_PAID) {
            return 'Paid registrations should be refunded instead of cancelled.';
        }

        if ($action === Payment::STATUS_CANCELLED && in_array($registration->status, ['approved', 'checked_in', 'completed'], true)) {
            return 'Approved, checked-in, or completed registrations should be refunded or rejected from the participant workflow instead of cancelled.';
        }

        if ($action === Payment::STATUS_SUBMITTED) {
            return 'Submitted proof status must come from the runner payment proof upload.';
        }

        if ($registration->payment_status === Payment::STATUS_EXPIRED && $action !== Payment::STATUS_PAID) {
            return 'Expired payments can only be reopened by marking them paid after verified payment.';
        }

        if ($registration->payment_status === Payment::STATUS_REFUNDED && ! in_array($action, [Payment::STATUS_PAID, Payment::STATUS_PENDING], true)) {
            return 'Refunded payments can only be reopened as pending or paid.';
        }

        if ($registration->payment_status === Payment::STATUS_CANCELLED && ! in_array($action, [Payment::STATUS_PENDING, Payment::STATUS_PAID], true)) {
            return 'Cancelled payments can only be reopened as pending or paid.';
        }

        if ($registration->status === 'rejected'
            && in_array($registration->payment_status, [Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED, Payment::STATUS_EXPIRED], true)
            && $action === Payment::STATUS_PAID) {
            return 'Reopen this registration from Participants before marking payment paid again.';
        }

        return null;
    }

    private function filteredRegistrationQuery(Request $request)
    {
        $user = $request->user();
        $managedEventIds = $user->managedEventIds();

        return Registration::query()
            ->with(['user', 'event.paymentMethods', 'category', 'latestPayment', 'payments' => function ($query) {
                $query->latest();
            }])
            ->where(function ($query) {
                $query->where('payment_required', true)
                    ->orWhere('payment_amount_cents', '>', 0)
                    ->orWhereHas('payments');
            })
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($managedEventIds) {
                $query->whereIn('event_id', $managedEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('payment_status', $request->string('status'));
            })
            ->when($request->filled('provider'), function ($query) use ($request) {
                $query->whereHas('payments', function ($paymentQuery) use ($request) {
                    $paymentQuery->where('provider', $request->string('provider'));
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('bib_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('event', function ($eventQuery) use ($search) {
                            $eventQuery->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('latestPayment', function ($paymentQuery) use ($search) {
                            $paymentQuery->where('provider_reference', 'like', "%{$search}%");
                        });
                });
            });
    }

    private function canAccessRegistration(Registration $registration, Request $request): bool
    {
        return $registration->event && $request->user()->canManageEvent($registration->event);
    }

    private function notifyRunnerAboutPaymentStatus(Registration $registration, string $status, bool $autoApproved = false): void
    {
        if (! $registration->user) {
            return;
        }

        $notificationCopy = $this->paymentNotificationCopy($registration, $status, $autoApproved);

        if (! $notificationCopy) {
            return;
        }

        $notification = PushNotification::create([
            'title' => $notificationCopy['title'],
            'message' => $notificationCopy['message'],
            'type' => 'payment',
            'target_audience' => 'runners',
            'target_user_id' => $registration->user_id,
            'data' => [
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
                'category_id' => (string) $registration->category_id,
                'payment_status' => $status,
                'registration_status' => (string) $registration->status,
                'screen' => 'payment',
            ],
            'is_active' => true,
        ]);

        try {
            $result = $this->messaging->sendNotification($notification, collect([$registration->user]));

            if ($result['sent'] > 0 || ($result['processed'] ?? false)) {
                $notification->update(['sent_at' => now()]);
            } elseif (! ($result['retry'] ?? false)) {
                $notification->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('Payment status notification could not be delivered immediately.', [
                'registration_id' => $registration->id,
                'payment_status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function paymentNotificationCopy(Registration $registration, string $status, bool $autoApproved = false): ?array
    {
        $eventTitle = $registration->event?->title ?: 'your event';

        return match ($status) {
            Payment::STATUS_PAID => [
                'title' => 'Payment Approved',
                'message' => $autoApproved
                    ? "Your payment for {$eventTitle} has been approved and your registration is confirmed."
                    : "Your payment for {$eventTitle} has been approved.",
            ],
            Payment::STATUS_FAILED => [
                'title' => 'Payment Needs Review',
                'message' => "Your payment proof for {$eventTitle} was not approved. Please submit a new proof or reference.",
            ],
            Payment::STATUS_WAIVED => [
                'title' => 'Payment Waived',
                'message' => "Your payment for {$eventTitle} has been waived.",
            ],
            Payment::STATUS_PENDING => [
                'title' => 'Payment Pending',
                'message' => "Your payment for {$eventTitle} is pending review.",
            ],
            Payment::STATUS_SUBMITTED => [
                'title' => 'Payment Proof Submitted',
                'message' => "Your payment proof for {$eventTitle} is waiting for admin review.",
            ],
            Payment::STATUS_REFUNDED => [
                'title' => 'Payment Refunded',
                'message' => "Your payment for {$eventTitle} has been marked as refunded.",
            ],
            Payment::STATUS_CANCELLED => [
                'title' => 'Payment Cancelled',
                'message' => "Your payment for {$eventTitle} has been cancelled.",
            ],
            default => null,
        };
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
