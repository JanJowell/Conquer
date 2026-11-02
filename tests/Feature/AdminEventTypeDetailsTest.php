<?php

use App\Http\Resources\Api\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Support\CutoffDuration;
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

    if ($type === 'Hiking') {
        $category['type_details'] = [
            'required_gear' => $details['required_gear'] ?? 'Hiking shoes and water',
        ];
        unset($details['required_gear']);
    }

    if ($type === 'Trail Run') {
        $category['type_details'] = [
            'trail_difficulty' => $details['trail_difficulty'] ?? null,
            'mandatory_gear' => $details['mandatory_gear'] ?? 'Hydration vest and whistle',
        ];
        unset($details['trail_difficulty'], $details['mandatory_gear']);
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

test('event manager can store the correct structured details for each event type', function (string $type, array $details, string $categoryLabel) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload($type, $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', $type.' Championship')->firstOrFail();
    expect($event->review_status)->toBe('draft');
    $event = approveEventSetupForTest($event);
    $category = $event->categories()->firstOrFail();
    $resource = (new EventResource($event))->toArray(Request::create('/api/events'));
    $expectedEventDetails = collect($details)
        ->reject(fn ($value, string $key) => Event::typeDetailSchema($type)[$key]['category_owned'] ?? false)
        ->all();

    expect($event->type_details)->toMatchArray($expectedEventDetails)
        ->and($event->categorySectionLabel())->toBe($categoryLabel)
        ->and($event->status)->toBe('upcoming')
        ->and($resource['type_details'])->toMatchArray($expectedEventDetails)
        ->and($resource['category_label'])->toBe($categoryLabel)
        ->and($resource['type_detail_items'])->not->toBeEmpty();

    if ($type === 'Hiking') {
        expect($category->type_details['required_gear'])->toBe($details['required_gear']);
    }

    if ($type === 'Trail Run') {
        expect($category->type_details['mandatory_gear'])->toBe($details['mandatory_gear'])
            ->and($category->type_details['trail_difficulty'])->toBe($details['trail_difficulty']);
    }
})->with('event type details');

test('cutoff presets keep the existing readable mobile field', function (string $duration) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $payload = eventTypePayload('Marathon', ['cutoff_time' => $duration]);
    // Inactive custom controls and other event types must not affect the selection.
    $payload['type_details']['Marathon']['cutoff_time_hours'] = 'ignored';
    $payload['type_details']['Marathon']['cutoff_time_minutes'] = 'ignored';
    $payload['type_details']['Trail Run'] = ['cutoff_time' => CutoffDuration::CUSTOM];
    $this->actingAs($admin)->post(route('admin.events.store'), $payload)->assertSessionHasNoErrors();
    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    expect($event->type_details)->toBe(['cutoff_time' => $duration])
        ->and($event->status)->toBe('draft');
    approveEventSetupForTest($event);
    $this->getJson('/api/events/'.$event->id)->assertOk()
        ->assertJsonPath('data.type_details.cutoff_time', $duration);
})->with(['30 minutes', '1 hour', '2 hours', '3 hours', '6 hours', '12 hours']);

test('custom cutoff hours and minutes are saved as a readable duration string', function (string $hours, string $minutes, string $expected) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), eventTypePayload('Marathon', [
        'cutoff_time' => CutoffDuration::CUSTOM,
        'cutoff_time_hours' => $hours,
        'cutoff_time_minutes' => $minutes,
    ]))->assertSessionHasNoErrors();
    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    expect($event->type_details)->toBe(['cutoff_time' => $expected]);
    approveEventSetupForTest($event);
    $this->getJson('/api/events/'.$event->id)->assertOk()
        ->assertJsonPath('data.type_details.cutoff_time', $expected);
})->with([
    ['0', '45', '45 minutes'],
    ['1', '0', '1 hour'],
    ['9', '30', '9 hours 30 minutes'],
    ['9', '45', '9 hours 45 minutes'],
    ['1', '1', '1 hour 1 minute'],
    ['0', '1', '1 minute'],
]);

test('invalid custom cutoffs do not create an event', function (mixed $hours, mixed $minutes, string $field) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), eventTypePayload('Marathon', [
        'cutoff_time' => CutoffDuration::CUSTOM,
        'cutoff_time_hours' => $hours,
        'cutoff_time_minutes' => $minutes,
    ]))->assertSessionHasErrors('type_details.Marathon.'.$field);
    expect(Event::count())->toBe(0);
})->with([
    [null, '30', 'cutoff_time_hours'],
    ['0', '0', 'cutoff_time_minutes'],
    ['-1', '30', 'cutoff_time_hours'],
    ['six', '30', 'cutoff_time_hours'],
    ['9.5', '30', 'cutoff_time_hours'],
    ['1000001', '30', 'cutoff_time_hours'],
    ['2', null, 'cutoff_time_minutes'],
    ['2', '60', 'cutoff_time_minutes'],
    ['2', '-1', 'cutoff_time_minutes'],
    ['2', '1.5', 'cutoff_time_minutes'],
    ['2', 'half', 'cutoff_time_minutes'],
    [['9'], '30', 'cutoff_time_hours'],
    ['2', ['30'], 'cutoff_time_minutes'],
]);

test('legacy cutoff text can be retained or replaced without changing category schedules', function (string $legacy) {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), eventTypePayload('Marathon', ['cutoff_time' => '6 hours']))
        ->assertSessionHasNoErrors();
    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    $event->update(['type_details' => ['cutoff_time' => $legacy]]);
    $category = $event->categories()->firstOrFail();
    $end = $category->scheduledEndAt()->toIso8601String();

    $response = $this->get(route('admin.events.edit', $event))->assertOk()
        ->assertSee('Existing: '.$legacy)->assertSee('Custom duration');
    if ($legacy === '9.5 hours') {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('string(//input[@id="type-detail-marathon-cutoff-time-hours"]/@value)'))->toBe('9')
            ->and($xpath->evaluate('string(//input[@id="type-detail-marathon-cutoff-time-minutes"]/@value)'))->toBe('30');
    }
    $payload = eventTypePayload('Marathon', ['cutoff_time' => $legacy]);
    unset($payload['categories']);
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->type_details['cutoff_time'])->toBe($legacy);

    $payload['type_details']['Marathon']['cutoff_time'] = 'some other free text';
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasErrors('type_details.Marathon.cutoff_time');
    expect($event->fresh()->type_details['cutoff_time'])->toBe($legacy);

    $payload['type_details']['Marathon'] = ['cutoff_time' => CutoffDuration::CUSTOM, 'cutoff_time_hours' => '9', 'cutoff_time_minutes' => '30'];
    $this->put(route('admin.events.update', $event), $payload)->assertSessionHasNoErrors();
    expect($event->fresh()->type_details)->toBe(['cutoff_time' => '9 hours 30 minutes'])
        ->and($category->fresh()->scheduledEndAt()->toIso8601String())->toBe($end)
        ->and($event->categories()->count())->toBe(1);

    $response = $this->get(route('admin.events.edit', $event))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(//select[@id="type-detail-marathon-cutoff-time"]/option[@selected]/@value)'))->toBe(CutoffDuration::CUSTOM)
        ->and($xpath->evaluate('string(//input[@id="type-detail-marathon-cutoff-time-hours"]/@value)'))->toBe('9')
        ->and($xpath->evaluate('string(//input[@id="type-detail-marathon-cutoff-time-minutes"]/@value)'))->toBe('30');
})->with(['6-8 hours, depending on wave', '9.5 hours', '1.33 hours']);

test('new events reject unstructured cutoff text while empty duration remains a draft', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($admin)->post(route('admin.events.store'), eventTypePayload('Marathon', ['cutoff_time' => 'at sunset']))
        ->assertSessionHasErrors('type_details.Marathon.cutoff_time');
    $this->post(route('admin.events.store'), eventTypePayload('Marathon', ['cutoff_time' => '']))->assertSessionHasNoErrors();
    $event = Event::where('title', 'Marathon Championship')->firstOrFail();
    expect($event->status)->toBe('draft')->and($event->type_details['cutoff_time'])->toBeNull();
});

test('type details reject invalid values for the selected event type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
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
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload($type, $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', $type.' Championship')->firstOrFail();
    expect($event->review_status)->toBe('draft');
    $event = approveEventSetupForTest($event);
    $formattedKeys = collect($event->formattedTypeDetails())->pluck('key');

    expect($event->status)->toBe('upcoming');

    foreach ($optionalKeys as $optionalKey) {
        expect($event->publicReadinessErrors())->not->toContain('add '.strtolower(str_replace('_', ' ', $optionalKey)))
            ->and($formattedKeys)->not->toContain($optionalKey);
    }
})->with('event types with optional route details');

test('event manager can create a multi-day event with category dates inside its range', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
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
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);

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
        ->assertDontSee('type_details[Hiking][required_gear]', false)
        ->assertDontSee('type_details[Trail Run][trail_difficulty]', false)
        ->assertDontSee('type_details[Trail Run][mandatory_gear]', false)
        ->assertDontSee('type_details[Triathlon][swim_distance_m]', false)
        ->assertDontSee('type_details[Duathlon][second_run_distance_km]', false)
        ->assertSee('categories[0][type_details][required_gear]', false)
        ->assertSee('categories[0][type_details][trail_difficulty]', false)
        ->assertSee('categories[0][type_details][mandatory_gear]', false)
        ->assertSee('categories[0][type_details][swim_distance_m]', false)
        ->assertSee('categories[0][type_details][second_run_distance_km]', false);

    expect(config('conquer.event_category_labels'))
        ->toMatchArray([
            'Cycling' => 'Ride Categories',
            'Hiking' => 'Hiking Routes / Registration Options',
            'Triathlon' => 'Competition Categories',
        ]);
});

test('event edit loads saved event and category details and updates without duplicating the category', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $details = [
        'trail_length_km' => 12,
        'difficulty' => 'Moderate',
        'elevation_gain_m' => 700,
        'estimated_duration' => '5 hours',
        'required_gear' => 'Hiking shoes, water, and rain jacket',
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), eventTypePayload('Hiking', $details))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Hiking Championship')->firstOrFail();
    $category = $event->categories()->firstOrFail();

    $this
        ->actingAs($admin)
        ->get(route('admin.events.edit', $event))
        ->assertOk()
        ->assertSee('name="categories[0][id]" value="'.$category->id.'"', false)
        ->assertSee('name="type_details[Hiking][difficulty]"', false)
        ->assertSee('<option value="Moderate" selected>Moderate</option>', false)
        ->assertSee('name="categories[0][type_details][required_gear]"', false)
        ->assertSee('Hiking shoes, water, and rain jacket');

    $updatePayload = eventTypePayload('Hiking', $details);
    $updatePayload['categories'][0]['id'] = $category->id;
    $updatePayload['categories'][0]['type_details']['required_gear'] = 'Hiking shoes, two liters of water, and a rain jacket';
    $updatePayload['categories'][0]['qualification_notes'] = 'Participants must be comfortable on steep trails.';
    $updatePayload['categories'][0]['slot_limit'] = 75;

    $this
        ->actingAs($admin)
        ->put(route('admin.events.update', $event), $updatePayload)
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHasNoErrors();

    expect($event->categories()->count())->toBe(1)
        ->and($category->fresh()->type_details['required_gear'])
        ->toBe('Hiking shoes, two liters of water, and a rain jacket')
        ->and($category->fresh()->qualification_notes)
        ->toBe('Participants must be comfortable on steep trails.')
        ->and($category->fresh()->slot_limit)->toBe(75);
});

test('category management heading follows the selected event type', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = Event::create([
        'manager_id' => $admin->id,
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
        ->assertSeeText('Ride Categories');
});

test('legacy events without structured details remain backward compatible', function () {
    $event = Event::forceCreate(['review_status' => null,
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
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
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
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
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
