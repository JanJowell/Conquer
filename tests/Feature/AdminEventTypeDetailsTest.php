<?php

use App\Http\Resources\Api\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

function eventTypePayload(string $type, array $details): array
{
    return [
        'title' => $type.' Championship',
        'description' => 'Complete event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Racetech Events',
        'interest_type' => $type,
        'type_details' => [$type => $details],
        'categories' => [[
            'category_type' => 'open',
            'distance_option' => '5',
            'slot_limit' => 100,
            'price_amount' => '0.00',
            'price_currency' => 'PHP',
            'status' => 'open',
        ]],
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

test('event form renders every type-specific field and dynamic category labels', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('type_details[Cycling][route_distance_km]', false)
        ->assertSee('type_details[Hiking][trail_length_km]', false)
        ->assertSee('type_details[Marathon][distances]', false)
        ->assertSee('type_details[Trail Run][mandatory_gear]', false)
        ->assertSee('type_details[Triathlon][swim_distance_m]', false)
        ->assertSee('type_details[Duathlon][second_run_distance_km]', false);

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
