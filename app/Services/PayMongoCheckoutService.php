<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoCheckoutService
{
    public function isConfigured(): bool
    {
        return filled(config('services.paymongo.secret_key'));
    }

    /**
     * @throws RequestException
     */
    public function createCheckoutSession(Registration $registration): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $registration->loadMissing(['user', 'event', 'category']);
        $eventTitle = $registration->event?->title ?: 'Conquer Event';
        $categoryName = $registration->category?->name ?: 'Race Registration';
        $amountCents = (int) ($registration->payment_amount_cents ?? 0);

        $response = Http::acceptJson()
            ->withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->post(rtrim((string) config('services.paymongo.base_url'), '/').'/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => "{$eventTitle} - {$categoryName}",
                        'success_url' => config('services.paymongo.success_url'),
                        'cancel_url' => config('services.paymongo.cancel_url'),
                        'payment_method_types' => config('services.paymongo.payment_methods'),
                        'line_items' => [
                            [
                                'currency' => $registration->payment_currency ?: 'PHP',
                                'amount' => $amountCents,
                                'name' => $categoryName,
                                'quantity' => 1,
                                'description' => $eventTitle,
                            ],
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return [
            'id' => $data['id'] ?? null,
            'checkout_url' => $attributes['checkout_url'] ?? null,
            'raw' => $response,
        ];
    }

    public function validWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        if (blank($signatureHeader)) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $providedSignature = str_starts_with((string) config('services.paymongo.secret_key'), 'sk_live_')
            ? $parts->get('li')
            : $parts->get('te');

        if (blank($timestamp) || blank($providedSignature)) {
            return false;
        }

        if (! ctype_digit((string) $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $secret);

        return hash_equals($expected, (string) $providedSignature);
    }
}
