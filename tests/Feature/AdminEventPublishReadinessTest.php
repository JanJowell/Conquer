<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\User;

function superAdminUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
}

function draftEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'City Fun Run',
        'slug' => 'city-fun-run-'.uniqid(),
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'status' => 'draft',
    ], $overrides));
}

function openCategoryFor(Event $event): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '5K',
        'distance_km' => 5,
        'slot_limit' => 100,
        'status' => 'open',
    ]);
}

function paidOpenCategoryFor(Event $event, array $overrides = []): Category
{
    return Category::create(array_merge([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'slot_limit' => 100,
        'price_cents' => 50000,
        'price_currency' => 'PHP',
        'status' => 'open',
    ], $overrides));
}

function completeEventPayload(Event $event, array $overrides = []): array
{
    return array_merge([
        'title' => $event->title,
        'description' => 'A community fun run for runners of all levels.',
        'venue' => $event->venue,
        'event_date' => $event->event_date->format('Y-m-d'),
        'start_time' => '06:00',
        'end_time' => '',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
        'type_details' => [
            'Cycling' => [
                'route_distance_km' => 50,
                'surface_type' => 'Road',
                'elevation_gain_m' => 600,
                'bike_type' => 'Road Bike',
                'helmet_required' => '1',
            ],
        ],
    ], $overrides);
}

test('an event stays draft until mobile-facing details are complete', function () {
    $admin = superAdminUser();
    $event = draftEvent();
    openCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event, [
            'description' => '',
            'start_time' => '',
            'registration_deadline' => '',
            'banner_image' => '',
            'organized_by' => '',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event stays draft until it has an open category', function () {
    $admin = superAdminUser();
    $event = draftEvent();

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event stays draft until its selected type details are complete', function () {
    $admin = superAdminUser();
    $event = draftEvent();
    openCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event, [
            'type_details' => [
                'Cycling' => [
                    'route_distance_km' => '',
                    'surface_type' => '',
                    'elevation_gain_m' => '',
                    'bike_type' => '',
                    'helmet_required' => '1',
                ],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft')
        ->and($event->fresh()->publicReadinessErrors())->toContain('add route distance');
});

test('a paid event stays draft until payment details are complete', function () {
    $admin = superAdminUser();
    $event = draftEvent();
    paidOpenCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event can be opened when details and an open category are ready', function () {
    $admin = superAdminUser();
    $event = draftEvent();
    openCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('upcoming');
});

test('a paid event can be opened when payment details are ready', function () {
    $admin = superAdminUser();
    $event = draftEvent();
    paidOpenCategoryFor($event, [
        'payment_provider' => 'GCash',
        'payment_account_name' => 'Conquer Events',
        'payment_account_number' => '09170000000',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('upcoming');
});

test('an event can be created with embedded open categories', function () {
    $admin = superAdminUser();

    $payload = completeEventPayload(new Event([
        'title' => 'Racetech Night Run',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth(),
    ]), [
        'title' => 'Racetech Night Run',
        'categories' => [
            [
                'category_type' => 'open',
                'distance_option' => '5',
                'slot_limit' => 100,
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'status' => 'open',
            ],
            [
                'category_type' => 'female',
                'distance_option' => '10',
                'slot_limit' => 50,
                'price_amount' => '250.00',
                'price_currency' => 'PHP',
                'payment_provider' => 'GCash',
                'payment_account_name' => 'Racetech Events',
                'payment_account_number' => '09170000000',
                'status' => 'open',
            ],
        ],
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Racetech Night Run')->first();

    expect($event)->not->toBeNull()
        ->and($event->fresh()->status)->toBe('upcoming')
        ->and($event->categories()->count())->toBe(2)
        ->and($event->categories()->pluck('name')->all())->toContain('5K Open', '10K Female');
});
