<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

function distanceUnitEvent(User $manager, string $title = 'Distance Unit Event'): Event
{
    return Event::create([
        'title' => $title.' '.uniqid(),
        'slug' => str($title.' '.uniqid())->slug(),
        'description' => 'Tests category distance units.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ]);
}

function distanceUnitCategoryPayload(Event $event, array $overrides = []): array
{
    return array_merge([
        'event_id' => $event->id,
        'category_type' => 'open',
        'distance_option' => '5',
        'distance_unit' => 'km',
        'scheduled_start_date' => $event->event_date->toDateString(),
        'scheduled_start_time' => '07:00',
        'scheduled_end_date' => $event->event_date->toDateString(),
        'scheduled_end_time' => '10:00',
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
    ], $overrides);
}

test('admin can create a category in miles while kilometers remain authoritative', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = distanceUnitEvent($admin);

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), distanceUnitCategoryPayload($event, [
            'distance_unit' => 'mi',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/events'));

    expect($category->distance_unit)->toBe('mi')
        ->and((float) $category->distance_km)->toBe(8.05)
        ->and($category->name)->toBe('5mi Open')
        ->and($category->formattedDistance())->toBe('5 mi')
        ->and($resource['distance_unit'])->toBe('mi')
        ->and($resource['distance_value'])->toBe(5.0)
        ->and($resource['distance_km'])->toBe(8.05);
});

test('legacy category requests without a unit continue to use kilometers', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = distanceUnitEvent($admin, 'Legacy Distance');
    $payload = distanceUnitCategoryPayload($event);
    unset($payload['distance_unit']);

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();

    expect($category->distance_unit)->toBe('km')
        ->and((float) $category->distance_km)->toBe(5.0)
        ->and($category->name)->toBe('5K Open');
});

test('admin can edit a custom category distance in miles', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = distanceUnitEvent($admin, 'Editable Distance');
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '5K Open',
        'distance_km' => 5,
        'distance_unit' => 'km',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), distanceUnitCategoryPayload($event, [
            'distance_option' => 'custom',
            'custom_distance_km' => '13.1',
            'distance_unit' => 'mi',
        ]))
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->distance_unit)->toBe('mi')
        ->and((float) $category->distance_km)->toBe(21.08)
        ->and($category->name)->toBe('13.1mi Open')
        ->and($category->formattedDistance())->toBe('13.1 mi');
});

test('embedded event creation stores the category distance unit', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $eventDate = now()->addMonth()->toDateString();
    $title = 'Miles Embedded Event '.uniqid();

    $this->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => $title,
            'description' => 'Event with a category measured in miles.',
            'venue' => 'Bacoor City',
            'event_date' => $eventDate,
            'event_end_date' => $eventDate,
            'start_time' => '06:00',
            'end_time' => '12:00',
            'registration_deadline' => now()->addWeek()->toDateString(),
            'banner_image' => 'events/banners/sample.jpg',
            'organized_by' => 'Racetech',
            'interest_type' => 'Marathon',
            'categories' => [[
                'category_type' => 'open',
                'distance_option' => '10',
                'distance_unit' => 'mi',
                'scheduled_start_date' => $eventDate,
                'scheduled_start_time' => '07:00',
                'scheduled_end_date' => $eventDate,
                'scheduled_end_time' => '10:00',
                'slot_limit' => 100,
                'price_amount' => '0.00',
                'price_currency' => 'PHP',
                'status' => 'open',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = Event::where('title', $title)->firstOrFail()->categories()->firstOrFail();

    expect($category->distance_unit)->toBe('mi')
        ->and((float) $category->distance_km)->toBe(16.09)
        ->and($category->name)->toBe('10mi Open');
});

test('unsupported category distance units are rejected', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = distanceUnitEvent($admin, 'Invalid Distance Unit');

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), distanceUnitCategoryPayload($event, [
            'distance_unit' => 'yards',
        ]))
        ->assertSessionHasErrors('distance_unit');

    expect($event->categories()->count())->toBe(0);
});

test('category forms expose kilometer and mile choices beside distance', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = distanceUnitEvent($admin, 'Distance Unit Form');

    $this->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('name="distance_unit"', false)
        ->assertSee('<option value="km"', false)
        ->assertSee('<option value="mi"', false);

    $this->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('name="categories[0][distance_unit]"', false);
});
