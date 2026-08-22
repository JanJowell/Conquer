<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;

function categoryGearEvent(User $manager, string $eventType, array $typeDetails = []): Event
{
    return Event::create([
        'title' => $eventType.' Gear Test '.uniqid(),
        'slug' => str($eventType.'-gear-test-'.uniqid())->slug(),
        'description' => 'Category-specific gear test event.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => $eventType,
        'type_details' => $typeDetails ?: null,
        'manager_id' => $manager->id,
    ]);
}

function categoryGearPayload(Event $event, string $gearKey, ?string $gear): array
{
    return [
        'event_id' => $event->id,
        'category_type' => 'open',
        'distance_option' => '10',
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '10:00',
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
        'type_details' => [$gearKey => $gear],
    ];
}

test('admin stores required hiking gear on the category and exposes it to mobile', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = categoryGearEvent($admin, 'Hiking');
    $payload = categoryGearPayload($event, 'required_gear', 'Trail shoes, water, and rain jacket');

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertRedirect(route('admin.categories.index', ['event_id' => $event->id]))
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/events'));

    expect($category->distance_km)->toEqual(10)
        ->and($category->type_details)->toBe(['required_gear' => $payload['type_details']['required_gear']])
        ->and($resource['type_details'])->toBe($category->type_details)
        ->and($resource['type_detail_items'][0])->toMatchArray([
            'key' => 'required_gear',
            'label' => 'Required Gear',
            'value' => $payload['type_details']['required_gear'],
        ]);
});

test('a new trail run category requires mandatory gear', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = categoryGearEvent($admin, 'Trail Run');

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), categoryGearPayload($event, 'mandatory_gear', null))
        ->assertSessionHasErrors('type_details.mandatory_gear')
        ->assertSessionDoesntHaveErrors('type_details.trail_difficulty');

    expect($event->categories()->count())->toBe(0);
});

test('legacy event gear remains a fallback while category gear takes precedence', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = categoryGearEvent($admin, 'Trail Run', [
        'trail_difficulty' => 'Moderate',
        'mandatory_gear' => 'Legacy hydration vest and whistle',
    ]);
    $legacyCategory = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);
    $specificCategory = Category::create([
        'event_id' => $event->id,
        'name' => '21K Open',
        'distance_km' => 21,
        'type_details' => [
            'trail_difficulty' => 'Technical',
            'mandatory_gear' => '21K vest, whistle, and headlamp',
        ],
        'status' => 'open',
    ]);

    $legacyResource = (new CategoryResource($legacyCategory))->toArray(Request::create('/api/events'));

    expect($legacyCategory->resolvedTypeDetails()['mandatory_gear'])->toBe('Legacy hydration vest and whistle')
        ->and($legacyCategory->resolvedTypeDetails()['trail_difficulty'])->toBe('Moderate')
        ->and($legacyResource['type_details']['mandatory_gear'])->toBe('Legacy hydration vest and whistle')
        ->and($legacyResource['type_details']['trail_difficulty'])->toBe('Moderate')
        ->and($specificCategory->resolvedTypeDetails()['mandatory_gear'])->toBe('21K vest, whistle, and headlamp')
        ->and($specificCategory->resolvedTypeDetails()['trail_difficulty'])->toBe('Technical')
        ->and($event->publicReadinessErrors())->not->toContain('add mandatory gear to every open category');
});

test('trail difficulty is optional but rejects unsupported values', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = categoryGearEvent($admin, 'Trail Run');
    $payload = categoryGearPayload($event, 'mandatory_gear', 'Hydration vest and whistle');
    $payload['type_details']['trail_difficulty'] = 'Impossible';

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasErrors('type_details.trail_difficulty');

    unset($payload['type_details']['trail_difficulty']);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasNoErrors();
});

test('admin can update trail difficulty and gear after a participant has registered', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $event = categoryGearEvent($admin, 'Trail Run');
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '10:00',
        'type_details' => [
            'trail_difficulty' => 'Moderate',
            'mandatory_gear' => 'Water and trail shoes',
        ],
        'status' => 'open',
    ]);
    Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'scheduled_start_time' => '07:00',
            'scheduled_end_time' => '10:00',
            'slot_limit' => 100,
            'price_amount' => '0.00',
            'price_currency' => 'PHP',
            'status' => 'open',
            'type_details' => [
                'trail_difficulty' => 'Technical',
                'mandatory_gear' => 'Water, trail shoes, and trekking poles',
            ],
        ])
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->type_details['trail_difficulty'])->toBe('Technical')
        ->and($category->type_details['mandatory_gear'])->toBe('Water, trail shoes, and trekking poles')
        ->and($category->distance_km)->toEqual(10)
        ->and($category->name)->toBe('10K Open');
});
