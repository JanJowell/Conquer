<?php

use App\Http\Resources\Api\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

function eventTypePayload(string $type, array $details): array
{
    $eventDate = now()->addMonth()->toDateString();
    $category = [
        'category_type' => 'open',
        'distance_option' => '5',
        'scheduled_start_time' => '06:00',
        'scheduled_end_time' => '10:00',
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
    ];

    if ($type === 'Triathlon') {
        $category['type_details'] = collect(['swim_distance_m', 'bike_distance_km', 'run_distance_km'])
            ->mapWithKeys(fn (string $key) => [$key => $details[$key]])
            ->all();
        unset($category['distance_option']);
    }

    if ($type === 'Duathlon') {
        $category['type_details'] = collect(['first_run_distance_km', 'bike_distance_km', 'second_run_distance_km'])
            ->mapWithKeys(fn (string $key) => [$key => $details[$key]])
            ->all();
        unset($category['distance_option']);
    }

    return [
        'title' => $type.' Championship',
        'description' => 'Complete event description.',
        'venue' => 'Bacoor City',
        'event_date' => $eventDate,
        'event_end_date' => $eventDate,
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Racetech Events',
        'interest_type' => $type,
        'type_details' => [$type => $details],
        'categories' => [$category],
    ];
}

dataset('event type details', [
    'cycling' => ['Cycling', [
        'route_distance_km' => 80,
        'surface_type' => 'Road',
        'elevation_gain_m' => 900,
        'bike_type' => 'Road Bike',
        'helmet_required' => '1',
    ], 'Ride Categories'],
    'hiking' => ['Hiking', [
        'trail_length_km' => 12,
        'difficulty' => 'Moderate',
        'elevation_gain_m' => 700,
        'estimated_duration' => '5 hours',
        'required_gear' => 'Hiking shoes, water, and rain jacket',
    ], 'Hiking Routes / Registration Options'],
    'marathon' => ['Marathon', [
        'distances' => '5K, 10K, 21K, 42K',
        'cutoff_time' => '7 hours',
    ], 'Race Categories'],
    'trail run' => ['Trail Run', [
        'distance_km' => 30,
        'trail_difficulty' => 'Technical',
        'elevation_gain_m' => 1500,
        'terrain' => 'Rocky mountain trail',
        'mandatory_gear' => 'Hydration vest and whistle',
        'cutoff_time' => '9 hours',
    ], 'Race Categories'],
    'triathlon' => ['Triathlon', [
        'swim_distance_m' => 1500,
        'swim_type' => 'Open Water',
        'bike_distance_km' => 40,
        'run_distance_km' => 10,
        'transition_details' => 'Two secured transition zones',
        'cutoff_time' => '5 hours',
    ], 'Competition Categories'],
    'duathlon' => ['Duathlon', [
        'first_run_distance_km' => 10,
        'bike_distance_km' => 40,
        'second_run_distance_km' => 5,
        'transition_details' => 'One secured transition zone',
    ], 'Competition Categories'],
]);

dataset('event types with optional route details', [
    'cycling elevation' => ['Cycling', [
        'route_distance_km' => 80,
        'surface_type' => 'Road',
        'bike_type' => 'Road Bike',
        'helmet_required' => '1',
    ], ['elevation_gain_m']],
    'hiking elevation' => ['Hiking', [
        'trail_length_km' => 12,
        'difficulty' => 'Moderate',
        'estimated_duration' => '5 hours',
        'required_gear' => 'Hiking shoes and water',
    ], ['elevation_gain_m']],
    'trail run elevation and terrain' => ['Trail Run', [
        'distance_km' => 30,
        'trail_difficulty' => 'Technical',
        'mandatory_gear' => 'Hydration vest and whistle',
        'cutoff_time' => '9 hours',
    ], ['elevation_gain_m', 'terrain']],
]);

test('admin can store the correct structured details for each event type', function (string $type, array $details, string $categoryLabel) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload($type, $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', $type.' Championship')->firstOrFail();
    $resource = (new EventResource($event))->toArray(Request::create('/api/events'));

    expect($event->type_details)->toMatchArray($details)
        ->and($event->categorySectionLabel())->toBe($categoryLabel)
        ->and($event->status)->toBe('upcoming')
        ->and($resource['type_details'])->toMatchArray($details)
        ->and($resource['category_label'])->toBe($categoryLabel)
        ->and($resource['type_detail_items'])->not->toBeEmpty();
})->with('event type details');

test('type details reject invalid values for the selected event type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $payload = eventTypePayload('Cycling', [
        'route_distance_km' => -5,
        'surface_type' => 'Underwater',
        'elevation_gain_m' => -1,
        'bike_type' => 'Spaceship',
        'helmet_required' => 'yes',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), $payload)
        ->assertSessionHasErrors([
            'type_details.Cycling.route_distance_km',
            'type_details.Cycling.surface_type',
            'type_details.Cycling.elevation_gain_m',
            'type_details.Cycling.bike_type',
            'type_details.Cycling.helmet_required',
        ]);
});

test('optional elevation and terrain details do not block publication or appear empty', function (string $type, array $details, array $optionalKeys) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload($type, $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', $type.' Championship')->firstOrFail();
    $formattedKeys = collect($event->formattedTypeDetails())->pluck('key');

    expect($event->status)->toBe('upcoming');

    foreach ($optionalKeys as $optionalKey) {
        expect($event->publicReadinessErrors())->not->toContain('add '.strtolower(str_replace('_', ' ', $optionalKey)))
            ->and($formattedKeys)->not->toContain($optionalKey);
    }
})->with('event types with optional route details');

test('admin can create a multi-day event with category dates inside its range', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $payload = eventTypePayload('Marathon', [
        'distances' => '5K and 10K',
        'cutoff_time' => '4 hours',
    ]);
    $payload['event_end_date'] = now()->addMonth()->addDays(2)->toDateString();
    $payload['end_time'] = '18:00';
    $payload['categories'][0]['scheduled_start_date'] = now()->addMonth()->addDay()->toDateString();
    $payload['categories'][0]['scheduled_end_date'] = now()->addMonth()->addDay()->toDateString();

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), $payload)
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    $resource = (new EventResource($event))->toArray(Request::create('/api/events'));

    expect($event->event_end_date->format('Y-m-d'))->toBe($payload['event_end_date'])
        ->and($event->categories()->firstOrFail()->scheduled_start_date->format('Y-m-d'))
        ->toBe($payload['categories'][0]['scheduled_start_date'])
        ->and($resource['event_start_date'])->toBe($payload['event_date'])
        ->and($resource['event_end_date'])->toBe($payload['event_end_date']);
});

test('event form renders every type-specific field and dynamic category labels', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('Event Start Date')
        ->assertSee('name="event_date"', false)
        ->assertSee('Event End Date')
        ->assertSee('name="event_end_date"', false)
        ->assertDontSee('type_details[Cycling][route_distance_km]', false)
        ->assertDontSee('type_details[Hiking][trail_length_km]', false)
        ->assertDontSee('type_details[Marathon][distances]', false)
        ->assertSee('type_details[Trail Run][mandatory_gear]', false)
        ->assertDontSee('type_details[Triathlon][swim_distance_m]', false)
        ->assertDontSee('type_details[Duathlon][second_run_distance_km]', false)
        ->assertSee('categories[0][type_details][swim_distance_m]', false)
        ->assertSee('categories[0][type_details][second_run_distance_km]', false);

    expect(config('conquer.event_category_labels'))
        ->toMatchArray([
            'Cycling' => 'Ride Categories',
            'Hiking' => 'Hiking Routes / Registration Options',
            'Triathlon' => 'Competition Categories',
        ]);
});

test('category management heading follows the selected event type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = Event::create([
        'title' => 'Sunday Ride',
        'slug' => 'sunday-ride',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth(),
        'status' => 'draft',
        'interest_type' => 'Cycling',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.index', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('<h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#151b26]">Ride Categories</h1>', false);
});

test('legacy events without structured details remain backward compatible', function () {
    $event = Event::create([
        'title' => 'Legacy Marathon',
        'slug' => 'legacy-marathon',
        'description' => 'Created before type-specific event details.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek(),
        'status' => 'draft',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Racetech Events',
        'interest_type' => 'Marathon',
        'type_details' => null,
    ]);

    $event->categories()->create([
        'name' => '42K Open',
        'distance_km' => 42,
        'status' => 'open',
    ]);

    expect($event->publicReadinessErrors())->toBeEmpty();
});

test('event schedule cannot be moved outside an existing category schedule', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $details = [
        'distances' => '5K, 10K, 21K, 42K',
        'cutoff_time' => '7 hours',
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload('Marathon', $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    $updatePayload = eventTypePayload('Marathon', $details);
    $updatePayload['start_time'] = '07:00';
    $updatePayload['end_time'] = '12:00';
    $updatePayload['categories'][0]['scheduled_start_time'] = '07:00';

    $this
        ->actingAs($admin)
        ->from(route('admin.events.edit', $event))
        ->put(route('admin.events.update', $event), $updatePayload)
        ->assertRedirect(route('admin.events.edit', $event))
        ->assertSessionHasErrors('start_time');

    expect($event->fresh()->start_time->format('H:i'))->toBe('06:00');
});

test('event end cannot be moved before an existing category cutoff end', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $details = [
        'distances' => '5K, 10K, 21K, 42K',
        'cutoff_time' => '7 hours',
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload('Marathon', $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    $updatePayload = eventTypePayload('Marathon', $details);
    $updatePayload['end_time'] = '09:00';
    $updatePayload['categories'][0]['scheduled_end_time'] = '09:00';

    $this
        ->actingAs($admin)
        ->from(route('admin.events.edit', $event))
        ->put(route('admin.events.update', $event), $updatePayload)
        ->assertRedirect(route('admin.events.edit', $event))
        ->assertSessionHasErrors('end_time');

    expect($event->fresh()->end_time)->toBeNull();
});
