<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RegistrationResource;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\EventPaymentMethod;
use App\Services\PayMongoCheckoutService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PayMongoCheckoutService $payMongo)
    {
    }

    public function history(Request $request, Registration $registration): JsonResponse
    {
        if ($registration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You cannot view payment history for this registration.',
            ], 403);
        }

        $registration->load(['event', 'category', 'latestPayment']);
        $payments = $registration->payments()
            ->latest()
            ->get();

        return response()->json([
            'registration' => new RegistrationResource($registration),
            'data' => $payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'provider_reference' => $payment->provider_reference,
                'status' => $payment->status,
                'amount_cents' => $payment->amount_cents,
                'amount' => number_format(($payment->amount_cents ?? 0) / 100, 2, '.', ''),
                'currency' => $payment->currency,
                'checkout_url' => $payment->checkout_url,
                'proof_url' => $payment->proof_path ? asset('storage/'.$payment->proof_path) : null,
                'notes' => $payment->payload['notes'] ?? null,
                'submitted_at' => optional($payment->submitted_at)?->toISOString(),
                'paid_at' => optional($payment->paid_at)?->toISOString(),
                'created_at' => optional($payment->created_at)?->toISOString(),
            ]),
        ]);
    }

    public function createPayMongoCheckout(Request $request, Registration $registration): JsonResponse
    {
        if ($registration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You cannot create payment checkout for this registration.',
            ], 403);
        }

        if ((int) ($registration->payment_amount_cents ?? 0) <= 0) {
            return response()->json([
                'message' => 'This registration does not require payment.',
            ], 422);
        }

        if ($registration->payment_status === Payment::STATUS_PAID) {
            return response()->json([
                'message' => 'This registration is already marked as paid.',
            ], 422);
        }

        if ($registration->status !== 'pending') {
            return response()->json([
                'message' => 'Online checkout can only be created while the registration is pending.',
            ], 422);
        }

        $registration->loadMissing('event.paymentMethods');
        if ($registration->event?->paymentMethods->isNotEmpty()
            && ! $registration->event->paymentMethods->contains(fn ($method) => $method->is_enabled && $method->provider === 'PayMongo')) {
            return response()->json([
                'message' => 'PayMongo is not enabled for this event.',
            ], 422);
        }

        if (! in_array($registration->payment_status, ['unpaid', Payment::STATUS_PENDING, Payment::STATUS_SUBMITTED, Payment::STATUS_FAILED], true)) {
            return response()->json([
                'message' => 'This payment can no longer create an online checkout.',
            ], 422);
        }

        try {
            $checkout = $this->payMongo->createCheckoutSession($registration);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (RequestException $e) {
            Log::warning('PayMongo checkout session could not be created.', [
                'registration_id' => $registration->id,
                'status' => $e->response?->status(),
                'body' => $e->response?->json(),
            ]);

            return response()->json([
                'message' => 'Online checkout could not be created right now. Please try again later.',
            ], 422);
        }

        if (blank($checkout['id'] ?? null) || blank($checkout['checkout_url'] ?? null)) {
            Log::warning('PayMongo checkout session response was missing required fields.', [
                'registration_id' => $registration->id,
                'response' => $checkout['raw'] ?? null,
            ]);

            return response()->json([
                'message' => 'Online checkout response was incomplete. Please try again later.',
            ], 422);
        }

        DB::transaction(function () use ($registration, $checkout) {
            $registration->update([
                'payment_required' => true,
                'payment_status' => Payment::STATUS_PENDING,
                'paid_at' => null,
            ]);

            Payment::create([
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'category_id' => $registration->category_id,
                'provider' => 'paymongo',
                'provider_reference' => $checkout['id'],
                'amount_cents' => $registration->payment_amount_cents ?? 0,
                'currency' => $registration->payment_currency ?? 'PHP',
                'status' => Payment::STATUS_PENDING,
                'checkout_url' => $checkout['checkout_url'],
                'payload' => [
                    'source' => 'paymongo_checkout',
                    'paymongo_checkout_session_id' => $checkout['id'],
                    'raw' => $checkout['raw'],
                ],
            ]);
        });

        $registration = $registration->fresh()->load(['event', 'category', 'latestPayment']);

        return response()->json([
            'message' => 'PayMongo checkout session created.',
            'checkout_url' => $checkout['checkout_url'],
            'data' => new RegistrationResource($registration),
        ]);
    }

    public function submitProof(Request $request, Registration $registration): JsonResponse
    {
        if ($registration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You cannot submit payment proof for this registration.',
            ], 403);
        }

        if ((int) ($registration->payment_amount_cents ?? 0) <= 0) {
            return response()->json([
                'message' => 'This registration does not require payment.',
            ], 422);
        }

        if ($registration->payment_status === Payment::STATUS_PAID) {
            return response()->json([
                'message' => 'This registration is already marked as paid.',
            ], 422);
        }

        if ($registration->status !== 'pending') {
            return response()->json([
                'message' => 'Payment proof can only be submitted while the registration is pending.',
            ], 422);
        }

        if (! in_array($registration->payment_status, ['unpaid', Payment::STATUS_PENDING, Payment::STATUS_SUBMITTED, Payment::STATUS_FAILED], true)) {
            return response()->json([
                'message' => 'This payment can no longer accept proof submissions.',
            ], 422);
        }

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'required_without:proof_image', 'string', 'max:255'],
            'proof_image' => ['nullable', 'required_without:provider_reference', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $registration->loadMissing(['event.paymentMethods', 'category']);
        $allOptions = $registration->event?->paymentMethods ?? collect();
        $enabledOptions = $allOptions->where('is_enabled', true)->values();
        $selectedOption = null;

        if ($allOptions->isNotEmpty()) {
            if (blank($validated['provider'] ?? null)) {
                return response()->json([
                    'message' => 'Select one of the event payment options before submitting proof.',
                ], 422);
            }

            $selectedOption = $enabledOptions->first(fn (EventPaymentMethod $method) =>
                strtolower($method->provider) === strtolower(trim((string) $validated['provider']))
            );

            if (! $selectedOption || $selectedOption->isOnlineCheckout()) {
                return response()->json([
                    'message' => 'Select a valid manual payment option for this event.',
                ], 422);
            }
        }

        $proofPath = $request->file('proof_image')?->store('payment-proofs', 'public');
        $provider = $selectedOption?->provider
            ?? (trim($validated['provider'] ?? '') ?: ($registration->category?->payment_provider ?: 'manual'));

        DB::transaction(function () use ($registration, $validated, $proofPath, $provider, $selectedOption) {
            $registration->update([
                'payment_required' => true,
                'payment_status' => Payment::STATUS_SUBMITTED,
                'paid_at' => null,
            ]);

            Payment::create([
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'category_id' => $registration->category_id,
                'provider' => $provider,
                'provider_reference' => $validated['provider_reference'] ?? null,
                'amount_cents' => $registration->payment_amount_cents ?? 0,
                'currency' => $registration->payment_currency ?? 'PHP',
                'status' => Payment::STATUS_SUBMITTED,
                'proof_path' => $proofPath,
                'submitted_at' => now(),
                'payload' => [
                    'notes' => $validated['notes'] ?? null,
                    'source' => 'mobile_payment_proof',
                    'event_payment_method_id' => $selectedOption?->id,
                ],
            ]);
        });

        return response()->json([
            'message' => 'Payment proof submitted for admin review.',
            'data' => new RegistrationResource(
                $registration->fresh()->load(['event', 'category', 'latestPayment'])
            ),
        ]);
    }

    public function payMongoWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->payMongo->validWebhookSignature($payload, $request->header('Paymongo-Signature'))) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $eventType = $request->input('data.attributes.type');
        $resource = $request->input('data.attributes.data', []);
        $checkoutSessionId = data_get($resource, 'id')
            ?: data_get($resource, 'attributes.checkout_session_id')
            ?: data_get($resource, 'attributes.checkout_session.id');

        if (blank($checkoutSessionId)) {
            return response()->json(['message' => 'Webhook accepted.']);
        }

        $payment = Payment::query()
            ->where('provider', 'paymongo')
            ->where('provider_reference', $checkoutSessionId)
            ->latest()
            ->first();

        if (! $payment) {
            Log::info('PayMongo webhook received for unknown checkout session.', [
                'checkout_session_id' => $checkoutSessionId,
                'event_type' => $eventType,
            ]);

            return response()->json(['message' => 'Webhook accepted.']);
        }

        if ($eventType === 'checkout_session.payment.paid') {
            $this->markPayMongoPaymentPaid($payment, $resource, $eventType);
        } elseif (in_array($eventType, ['checkout_session.payment.failed', 'checkout_session.expired'], true)) {
            $this->markPayMongoPaymentFailed($payment, $resource, $eventType);
        }

        return response()->json(['message' => 'Webhook processed.']);
    }

    private function markPayMongoPaymentPaid(Payment $payment, array $resource, string $eventType): void
    {
        DB::transaction(function () use ($payment, $resource, $eventType) {
            $registration = Registration::query()
                ->whereKey($payment->registration_id)
                ->lockForUpdate()
                ->first();

            if (! $registration || $registration->payment_status === Payment::STATUS_PAID) {
                return;
            }

            $now = now();
            $updates = [
                'payment_status' => Payment::STATUS_PAID,
                'payment_required' => true,
                'paid_at' => $now,
            ];

            if ($registration->status === 'pending') {
                $updates['status'] = 'approved';
                $updates['rejection_reason'] = null;

                if (blank($registration->bib_number)) {
                    $updates['bib_number'] = $this->nextBibNumberForEvent($registration->event_id);
                }
            }

            $registration->update($updates);

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => $now,
                'payload' => array_merge($payment->payload ?? [], [
                    'webhook_event_type' => $eventType,
                    'webhook_resource' => $resource,
                ]),
            ]);
        });
    }

    private function markPayMongoPaymentFailed(Payment $payment, array $resource, string $eventType): void
    {
        DB::transaction(function () use ($payment, $resource, $eventType) {
            $registration = $payment->registration;

            if ($registration && $registration->payment_status !== Payment::STATUS_PAID) {
                $registration->update([
                    'payment_status' => Payment::STATUS_FAILED,
                    'paid_at' => null,
                ]);
            }

            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'paid_at' => null,
                'payload' => array_merge($payment->payload ?? [], [
                    'webhook_event_type' => $eventType,
                    'webhook_resource' => $resource,
                ]),
            ]);
        });
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
