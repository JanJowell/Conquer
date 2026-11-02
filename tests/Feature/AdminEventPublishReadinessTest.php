<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\User;

function eventSetupManager(): User
{
    return User::factory()->create([
        'role' => User::ROLE_EVENT_MANAGER,
    ]);
}

function draftEvent(User $manager, array $overrides = []): Event
{
    return Event::create(array_merge([
        'manager_id' => $manager->id,
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
    $admin = eventSetupManager();
    $event = draftEvent($admin);
    openCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event, [
            'description' => '',
            'start_time' => '',
            'registration_deadline' => '',
            'banner_image' => '',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event stays draft until it has an open category', function () {
    $admin = eventSetupManager();
    $event = draftEvent($admin);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event stays draft until its selected type details are complete', function () {
    $admin = eventSetupManager();
    $event = draftEvent($admin);
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
        ->and($event->fresh()->publicReadinessErrors())->toContain('add surface type');
});

test('a paid event stays draft until payment details are complete', function () {
    $admin = eventSetupManager();
    $event = draftEvent($admin);
    paidOpenCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
});

test('an event can be opened when details and an open category are ready', function () {
    $admin = eventSetupManager();
    $event = draftEvent($admin);
    openCategoryFor($event);

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event))
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->status)->toBe('draft');
    approveEventSetupForTest($event);
    expect($event->fresh()->status)->toBe('upcoming');
});

test('a paid event can be opened when payment details are ready', function () {
    $admin = eventSetupManager();
    $event = draftEvent($admin);
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

    expect($event->fresh()->status)->toBe('draft');
    approveEventSetupForTest($event);
    expect($event->fresh()->status)->toBe('upcoming');
});

test('an event can be created and published without an organizer while assigning a manager', function (array $legacyInput) {
    $admin = eventSetupManager();
    $manager = $admin;

    $payload = completeEventPayload(new Event([
        'title' => 'Racetech Night Run',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth(),
    ]), [
        'title' => 'Racetech Night Run',
        'manager_id' => $manager->id,
        'categories' => [
            [
                'category_type' => 'open',
                'distance_option' => '5',
                'scheduled_start_time' => '06:00',
                'scheduled_end_time' => '10:00',
                'slot_limit' => 100,
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'status' => 'open',
            ],
            [
                'category_type' => 'female',
                'distance_option' => '10',
                'scheduled_start_time' => '06:30',
                'scheduled_end_time' => '11:00',
                'slot_limit' => 50,
                'price_amount' => '250.00',
                'price_currency' => 'PHP',
                'payment_provider' => 'GCash',
                'payment_account_name' => 'Racetech Events',
                'payment_account_number' => '09170000000',
                'status' => 'open',
            ],
        ],
    ] + $legacyInput);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Racetech Night Run')->first();

    expect($event->fresh()->status)->toBe('draft');
    approveEventSetupForTest($event);
    expect($event)->not->toBeNull()
        ->and($event->fresh()->status)->toBe('upcoming')
        ->and($event->fresh()->organized_by)->toBeNull()
        ->and($event->fresh()->manager_id)->toBe($manager->id)
        ->and($event->categories()->count())->toBe(2)
        ->and($event->categories()->pluck('name')->all())->toContain('5K Open', '10K Female');

    $this->getJson('/api/events/'.$event->id)->assertOk()
        ->assertJsonStructure(['data' => ['organized_by']])
        ->assertJsonPath('data.organized_by', null);
})->with([
    'current form' => [[]],
    'older form with retired field' => [['organized_by' => 'Ignored organizer input']],
]);

test('event updates preserve historical organizer data and the mobile response', function (array $legacyInput) {
    $admin = eventSetupManager();
    $manager = $admin;
    $event = draftEvent($admin, ['organized_by' => 'Historical organizer', 'manager_id' => $manager->id]);
    openCategoryFor($event);

    $this->actingAs($admin)
        ->put(route('admin.events.update', $event), completeEventPayload($event, $legacyInput))
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    approveEventSetupForTest($event);
    expect($event->fresh()->organized_by)->toBe('Historical organizer')
        ->and($event->fresh()->manager_id)->toBe($manager->id)
        ->and($event->fresh()->status)->toBe('upcoming');
    $this->getJson('/api/events/'.$event->id)->assertOk()
        ->assertJsonPath('data.organized_by', 'Historical organizer');
})->with([
    'field omitted' => [[]],
    'older form attempts replacement' => [['organized_by' => 'Replacement organizer']],
]);

test('event manager event forms and displays omit the retired organizer and retain manager assignment', function () {
    $admin = eventSetupManager();
    $manager = $admin;
    $manager->update(['name' => 'Assigned Test Manager']);
    $event = draftEvent($admin, ['organized_by' => 'Historical organizer', 'manager_id' => $manager->id]);
    $this->actingAs($admin);

    foreach ([route('admin.events.create'), route('admin.events.edit', $event)] as $url) {
        $this->get($url)->assertOk()
            ->assertDontSee('name="organized_by"', false)->assertDontSee('Organized By')
            ->assertDontSee('Historical organizer')->assertDontSee('add the organizer')
            ->assertDontSee('name="manager_id"', false);
    }
    foreach ([route('admin.events.index'), route('admin.events.show', $event)] as $url) {
        $this->get($url)->assertOk()
            ->assertDontSee('name="organized_by"', false)->assertDontSee('Organized By')
            ->assertDontSee('Organizer')->assertDontSee('Historical organizer')
            ->assertDontSee('add the organizer')->assertSee('Assigned Test Manager');
    }
});

test('an event can be created with an embedded group category', function () {
    $admin = eventSetupManager();

    $payload = completeEventPayload(new Event([
        'title' => 'Racetech Group Run',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth(),
    ]), [
        'title' => 'Racetech Group Run',
        'categories' => [[
            'category_type' => 'group',
            'distance_option' => '5',
            'scheduled_start_time' => '06:00',
            'scheduled_end_time' => '10:00',
            'group_min_members' => 3,
            'group_max_members' => 8,
            'slot_limit' => 100,
            'price_amount' => '0.00',
            'price_currency' => 'PHP',
            'status' => 'open',
        ]],
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = Event::where('title', 'Racetech Group Run')->firstOrFail()->categories()->firstOrFail();

    expect($category->name)->toBe('5K Group')
        ->and($category->participation_mode)->toBe(Category::PARTICIPATION_GROUP)
        ->and($category->group_min_members)->toBe(3)
        ->and($category->group_max_members)->toBe(8);
});
