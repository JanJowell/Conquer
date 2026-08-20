<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;

function eventPaymentTestEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Multiple Payment Run',
        'slug' => 'multiple-payment-run-'.uniqid(),
        'description' => 'A complete event with multiple payment choices.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => 'Cycling',
        'type_details' => [
            'route_distance_km' => 50,
            'surface_type' => 'Road',
            'bike_type' => 'Road Bike',
            'helmet_required' => true,
        ],
    ], $overrides));
}

function eventPaymentTestCategory(Event $event, array $overrides = []): Category
{
    return Category::create(array_merge([
        'event_id' => $event->id,
        'name' => '50K Open',
        'distance_km' => 50,
        'slot_limit' => 100,
        'price_cents' => 75000,
        'price_currency' => 'PHP',
        'status' => 'open',
    ], $overrides));
}

function eventPaymentUpdatePayload(Event $event, array $overrides = []): array
{
    return array_merge([
        'title' => $event->title,
        'description' => $event->description,
        'venue' => $event->venue,
        'event_date' => $event->event_date->format('Y-m-d'),
        'event_end_date' => ($event->event_end_date ?? $event->event_date)->format('Y-m-d'),
        'start_time' => '06:00',
        'end_time' => '',
        'registration_deadline' => $event->registration_deadline->format('Y-m-d'),
        'banner_image' => $event->banner_image,
        'organized_by' => $event->organized_by,
        'interest_type' => 'Cycling',
        'type_details' => [
            'Cycling' => [
                'route_distance_km' => 50,
                'surface_type' => 'Road',
                'elevation_gain_m' => '',
                'bike_type' => 'Road Bike',
                'helmet_required' => '1',
            ],
        ],
        'payment_methods_submitted' => '1',
    ], $overrides);
}

test('admin can configure multiple event payment options shared by every paid category', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = eventPaymentTestEvent();
    $category = eventPaymentTestCategory($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), eventPaymentUpdatePayload($event, [
            'payment_methods' => [
                [
                    'provider' => 'GCash',
                    'account_name' => 'Conquer Events',
                    'account_number' => '09170000000',
                    'instructions' => 'Include your registration number.',
                    'is_enabled' => '1',
                ],
                [
                    'provider' => 'Maya',
                    'account_name' => 'Conquer Events',
                    'account_number' => '09180000000',
                    'instructions' => 'Upload the receipt after paying.',
                    'is_enabled' => '1',
                ],
            ],
        ]))
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('upcoming')
        ->and($event->paymentMethods()->pluck('provider')->all())->toBe(['GCash', 'Maya'])
        ->and($category->fresh()->payment_provider)->toBeNull();

    $this
        ->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.payment_options')
        ->assertJsonPath('data.payment_options.0.provider', 'GCash')
        ->assertJsonPath('data.payment_options.1.provider', 'Maya')
        ->assertJsonCount(2, 'data.categories.0.payment_options')
        ->assertJsonPath('data.categories.0.payment_instructions.provider', 'GCash');
});

test('event payment options reject duplicate providers and incomplete manual accounts', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = eventPaymentTestEvent();

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), eventPaymentUpdatePayload($event, [
            'payment_methods' => [
                ['provider' => 'GCash', 'account_name' => '', 'account_number' => '', 'is_enabled' => '1'],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors([
            'payment_methods.0.account_name',
            'payment_methods.0.account_number',
        ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), eventPaymentUpdatePayload($event, [
            'payment_methods' => [
                ['provider' => 'GCash', 'account_name' => 'Conquer Events', 'account_number' => '09170000000', 'is_enabled' => '1'],
                ['provider' => 'GCash', 'account_name' => 'Other Account', 'account_number' => '09990000000', 'is_enabled' => '1'],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors([
            'payment_methods.0.provider',
            'payment_methods.1.provider',
        ]);

    expect($event->paymentMethods()->count())->toBe(0);
});

test('disabled event options do not reactivate old category payment details', function () {
    $event = eventPaymentTestEvent();
    $category = eventPaymentTestCategory($event, [
        'payment_provider' => 'GCash',
        'payment_account_name' => 'Legacy Account',
        'payment_account_number' => '09171111111',
    ]);

    $event->paymentMethods()->create([
        'provider' => 'GCash',
        'account_name' => 'Current Account',
        'account_number' => '09172222222',
        'is_enabled' => false,
    ]);

    $payload = (new CategoryResource($category->load('event.paymentMethods')))->resolve(request());

    expect($event->hasUsablePaymentOptions(collect([$category])))->toBeFalse()
        ->and($payload['payment_options']->resource->isEmpty())->toBeTrue()
        ->and($payload['payment_instructions'])->toBeNull();
});

test('manual payment proof must use an enabled event payment option', function () {
    $token = 'event-payment-option-token';
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
    $event = eventPaymentTestEvent(['status' => 'upcoming']);
    $category = eventPaymentTestCategory($event);
    $gcash = $event->paymentMethods()->create([
        'provider' => 'GCash',
        'account_name' => 'Conquer Events',
        'account_number' => '09170000000',
        'is_enabled' => true,
    ]);
    $event->paymentMethods()->create([
        'provider' => 'Maya',
        'account_name' => 'Conquer Events',
        'account_number' => '09180000000',
        'is_enabled' => false,
        'sort_order' => 1,
    ]);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'payment_required' => true,
        'payment_status' => 'unpaid',
        'payment_amount_cents' => 75000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/registrations/{$registration->id}/payment-proof", [
            'provider' => 'Maya',
            'provider_reference' => 'MAYA-DISABLED',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Select a valid manual payment option for this event.');

    $this
        ->withToken($token)
        ->postJson("/api/registrations/{$registration->id}/payment-proof", [
            'provider' => 'GCash',
            'provider_reference' => 'GCASH-ENABLED',
        ])
        ->assertOk()
        ->assertJsonPath('data.latest_payment.provider', 'GCash');

    $payment = Payment::where('registration_id', $registration->id)->sole();

    expect(data_get($payment->payload, 'event_payment_method_id'))->toBe($gcash->id);
});

test('admin forms keep fees on categories and payment accounts on events', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = eventPaymentTestEvent();
    $category = eventPaymentTestCategory($event);

    $this
        ->actingAs($admin)
        ->get(route('admin.events.edit', $event))
        ->assertOk()
        ->assertSee('Event Payment Options')
        ->assertSee('These options apply to every paid category')
        ->assertSee('payment_methods[0][provider]', false)
        ->assertDontSee('categories[0][payment_provider]', false);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee('Registration Fee')
        ->assertDontSee('name="payment_provider"', false)
        ->assertDontSee('Payment Details');
});
