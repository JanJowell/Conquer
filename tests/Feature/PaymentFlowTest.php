<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function paymentRunnerWithToken(string $token = 'payment-mobile-token'): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
}

function paymentReadyEvent(?User $manager = null): Event
{
    return Event::create([
        'title' => 'Payment Ready Run',
        'slug' => 'payment-ready-run-'.uniqid(),
        'description' => 'A complete public event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
        'manager_id' => $manager?->id,
    ]);
}

function paymentCategoryFor(Event $event, int $priceCents = 0): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => $priceCents > 0 ? '10K Paid' : '5K Free',
        'distance_km' => $priceCents > 0 ? 10 : 5,
        'slot_limit' => 100,
        'price_cents' => $priceCents,
        'price_currency' => 'PHP',
        'payment_provider' => $priceCents > 0 ? 'GCash' : null,
        'payment_account_name' => $priceCents > 0 ? 'Conquer Events' : null,
        'payment_account_number' => $priceCents > 0 ? '09170000000' : null,
        'status' => 'open',
    ]);
}

test('free category registration has waived payment status', function () {
    $token = 'free-payment-token';
    paymentRunnerWithToken($token);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_required', false)
        ->assertJsonPath('data.payment_status', 'waived')
        ->assertJsonPath('data.payment_amount_cents', 0);
});

test('paid category registration starts unpaid and requires payment', function () {
    $token = 'paid-payment-token';
    paymentRunnerWithToken($token);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 75000);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'L',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_required', true)
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.payment_amount_cents', 75000)
        ->assertJsonPath('data.payment_currency', 'PHP');
});

test('runner can submit manual payment proof for paid registration', function () {
    $token = 'proof-payment-token';
    $runner = paymentRunnerWithToken($token);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 50000);

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => 'unpaid',
        'payment_amount_cents' => 50000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/registrations/{$registration->id}/payment-proof", [
            'provider' => 'GCash',
            'provider_reference' => 'GCASH-12345',
            'notes' => 'Paid through mobile wallet.',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', Payment::STATUS_SUBMITTED)
        ->assertJsonPath('data.latest_payment.provider_reference', 'GCASH-12345')
        ->assertJsonPath('data.latest_payment.status', Payment::STATUS_SUBMITTED);

    expect($registration->fresh()->payment_status)->toBe(Payment::STATUS_SUBMITTED);
    expect(Payment::where('registration_id', $registration->id)->count())->toBe(1);
});

test('admin can mark paid registration as paid and approve participant', function () {
    $admin = User::factory()->create();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 60000);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => Payment::STATUS_SUBMITTED,
        'payment_amount_cents' => 60000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.payments.update', $registration), [
            'action' => Payment::STATUS_PAID,
            'provider' => 'GCash',
            'provider_reference' => 'GCASH-PAID-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Payment marked paid and participant approved successfully.');

    $registration->refresh();

    expect($registration->payment_status)->toBe(Payment::STATUS_PAID);
    expect($registration->status)->toBe('approved');
    expect($registration->bib_number)->toBe('001');
    expect($registration->paid_at)->not->toBeNull();
    expect($registration->latestPayment()->first()->provider_reference)->toBe('GCASH-PAID-1');
});

test('event manager cannot update payments for unassigned events', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 60000);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => Payment::STATUS_SUBMITTED,
        'payment_amount_cents' => 60000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->patch(route('admin.payments.update', $registration), [
            'action' => Payment::STATUS_PAID,
        ])
        ->assertForbidden();

    expect($registration->fresh()->payment_status)->toBe(Payment::STATUS_SUBMITTED);
});

test('payments page loads for admins', function () {
    $admin = User::factory()->create();

    $this
        ->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('Payments');
});

test('payments page shows paymongo checkout details and provider filter', function () {
    $admin = User::factory()->create();
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 80000);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => Payment::STATUS_PENDING,
        'payment_amount_cents' => 80000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    Payment::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'provider' => 'paymongo',
        'provider_reference' => 'cs_test_visible',
        'amount_cents' => 80000,
        'currency' => 'PHP',
        'status' => Payment::STATUS_PAID,
        'checkout_url' => 'https://checkout.paymongo.test/cs_test_visible',
        'payload' => [
            'source' => 'paymongo_checkout',
            'webhook_event_type' => 'checkout_session.payment.paid',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.payments.index', ['provider' => 'paymongo']))
        ->assertOk()
        ->assertSee('Gateway')
        ->assertSee('cs_test_visible')
        ->assertSee('Open Checkout')
        ->assertSee('Paymongo Checkout')
        ->assertSee('checkout_session.payment.paid');
});

test('runner can create a paymongo checkout session for paid registration', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.base_url' => 'https://api.paymongo.test',
        'services.paymongo.payment_methods' => ['gcash', 'paymaya', 'card'],
    ]);

    Http::fake([
        'https://api.paymongo.test/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_123',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.test/cs_test_123',
                ],
            ],
        ]),
    ]);

    $token = 'paymongo-checkout-token';
    $runner = paymentRunnerWithToken($token);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 80000);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => 'unpaid',
        'payment_amount_cents' => 80000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/registrations/{$registration->id}/paymongo-checkout")
        ->assertOk()
        ->assertJsonPath('checkout_url', 'https://checkout.paymongo.test/cs_test_123')
        ->assertJsonPath('data.payment_status', Payment::STATUS_PENDING)
        ->assertJsonPath('data.latest_payment.provider', 'paymongo')
        ->assertJsonPath('data.latest_payment.provider_reference', 'cs_test_123')
        ->assertJsonPath('data.latest_payment.checkout_url', 'https://checkout.paymongo.test/cs_test_123');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization')
        && $request['data']['attributes']['line_items'][0]['amount'] === 80000);
});

test('paymongo paid webhook marks registration paid and approved', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.webhook_secret' => 'whsec_test_123',
    ]);

    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = paymentReadyEvent();
    $category = paymentCategoryFor($event, 90000);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => Payment::STATUS_PENDING,
        'payment_amount_cents' => 90000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    Payment::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'provider' => 'paymongo',
        'provider_reference' => 'cs_test_paid',
        'amount_cents' => 90000,
        'currency' => 'PHP',
        'status' => Payment::STATUS_PENDING,
        'checkout_url' => 'https://checkout.paymongo.test/cs_test_paid',
    ]);

    $payload = json_encode([
        'data' => [
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => 'cs_test_paid',
                    'type' => 'checkout_session',
                ],
            ],
        ],
    ]);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $this
        ->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Webhook processed.');

    $registration->refresh();

    expect($registration->payment_status)->toBe(Payment::STATUS_PAID);
    expect($registration->status)->toBe('approved');
    expect($registration->bib_number)->toBe('001');
    expect($registration->paid_at)->not->toBeNull();
    expect($registration->latestPayment()->first()->status)->toBe(Payment::STATUS_PAID);
});

test('paymongo webhook rejects requests when its secret is not configured', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.webhook_secret' => null,
    ]);

    $this
        ->postJson('/api/paymongo/webhook', [
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => 'cs_test_paid',
                        'type' => 'checkout_session',
                    ],
                ],
            ],
        ])
        ->assertUnauthorized();
});

test('paymongo webhook signature is verified when secret is configured', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.webhook_secret' => 'whsec_test_123',
    ]);

    $payload = json_encode([
        'data' => [
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => 'unknown_session'],
            ],
        ],
    ]);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $this
        ->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $payload)
        ->assertOk();

    $this
        ->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=bad-signature,li=",
        ], $payload)
        ->assertUnauthorized();

    $staleTimestamp = (string) now()->subMinutes(6)->timestamp;
    $staleSignature = hash_hmac('sha256', $staleTimestamp.'.'.$payload, 'whsec_test_123');

    $this
        ->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$staleTimestamp},te={$staleSignature},li=",
        ], $payload)
        ->assertUnauthorized();
});

test('api config reports payment gateway availability', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_123',
        'services.paymongo.public_key' => 'pk_test_123',
        'services.paymongo.webhook_secret' => 'whsec_test_123',
        'services.paymongo.payment_methods' => ['gcash', 'paymaya', 'card'],
    ]);

    $this
        ->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('payments.manual_proof_enabled', true)
        ->assertJsonPath('payments.paymongo_enabled', true)
        ->assertJsonPath('payments.paymongo_public_key_configured', true)
        ->assertJsonPath('payments.paymongo_webhook_configured', true)
        ->assertJsonPath('payments.paymongo_payment_methods.0', 'gcash');
});

test('paymongo checkout return pages render', function () {
    $this
        ->get(route('payments.success'))
        ->assertOk()
        ->assertSee('Payment Submitted');

    $this
        ->get(route('payments.cancelled'))
        ->assertOk()
        ->assertSee('Payment Cancelled');
});
