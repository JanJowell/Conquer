<?php

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

function scheduledCategoryEvent(User $manager): Event
{
    return Event::create([
        'title' => 'Scheduled Category Event '.uniqid(),
        'slug' => 'scheduled-category-event-'.uniqid(),
        'description' => 'Tests category wave schedules.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ]);
}

function scheduledCategoryPayload(Event $event, string $scheduledStartTime, string $scheduledEndTime = '10:00'): array
{
    return [
        'event_id' => $event->id,
        'category_type' => 'open',
        'distance_option' => '10',
        'scheduled_start_time' => $scheduledStartTime,
        'scheduled_end_time' => $scheduledEndTime,
        'slot_limit' => 100,
        'price_amount' => '0.00',
        'price_currency' => 'PHP',
        'status' => 'open',
    ];
}

test('admin can save a category on the second day of a multi-day event', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $event->update(['event_end_date' => $event->event_date->copy()->addDay()]);
    $secondDay = $event->event_end_date->format('Y-m-d');
    $payload = scheduledCategoryPayload($event, '07:30', '10:00') + [
        'scheduled_start_date' => $secondDay,
        'scheduled_end_date' => $secondDay,
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();

    expect($category->scheduledStartAt()->format('Y-m-d H:i'))->toBe($secondDay.' 07:30')
        ->and($category->scheduledEndAt()->format('Y-m-d H:i'))->toBe($secondDay.' 10:00');
});

test('an overnight category is valid inside a multi-day event', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $event->update(['event_end_date' => $event->event_date->copy()->addDay()]);
    $payload = scheduledCategoryPayload($event, '23:00', '02:00') + [
        'scheduled_start_date' => $event->event_date->format('Y-m-d'),
        'scheduled_end_date' => $event->event_end_date->format('Y-m-d'),
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();

    expect($category->scheduledStartAt()->lt($category->scheduledEndAt()))->toBeTrue();
});

test('a category cannot be scheduled outside a multi-day event date range', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $event->update(['event_end_date' => $event->event_date->copy()->addDay()]);
    $outsideDate = $event->event_end_date->copy()->addDay()->format('Y-m-d');
    $payload = scheduledCategoryPayload($event, '07:30', '10:00') + [
        'scheduled_start_date' => $outsideDate,
        'scheduled_end_date' => $outsideDate,
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasErrors('scheduled_start_time');

    expect($event->categories()->count())->toBe(0);
});

test('admin can save a category-specific gun start and cutoff end time', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), scheduledCategoryPayload($event, '07:30'))
        ->assertRedirect(route('admin.categories.index', ['event_id' => $event->id]))
        ->assertSessionHasNoErrors();

    $category = Category::where('event_id', $event->id)->firstOrFail();

    expect($category->scheduled_start_time->format('H:i'))->toBe('07:30')
        ->and($category->scheduled_end_time->format('H:i'))->toBe('10:00')
        ->and($category->scheduledStartAt()->format('Y-m-d H:i'))->toBe($event->event_date->format('Y-m-d').' 07:30')
        ->and($category->scheduledEndAt()->format('Y-m-d H:i'))->toBe($event->event_date->format('Y-m-d').' 10:00');
});

test('triathlon category stores component distances and calculates its total distance', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $event->update(['interest_type' => 'Triathlon']);
    $payload = scheduledCategoryPayload($event, '07:30');
    unset($payload['distance_option']);
    $payload['type_details'] = [
        'swim_distance_m' => 1500,
        'bike_distance_km' => 40,
        'run_distance_km' => 10,
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasNoErrors();

    $category = $event->categories()->firstOrFail();
    $resource = (new CategoryResource($category))->toArray(Request::create('/api/categories'));

    expect((float) $category->distance_km)->toBe(51.5)
        ->and($category->type_details)->toMatchArray($payload['type_details'])
        ->and($resource['distance_km'])->toBe(51.5)
        ->and(collect($resource['type_detail_items'])->pluck('key')->all())
        ->toBe(['swim_distance_m', 'bike_distance_km', 'run_distance_km']);
});

test('multisport category requires every component distance', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $event->update(['interest_type' => 'Duathlon']);
    $payload = scheduledCategoryPayload($event, '07:30');
    unset($payload['distance_option']);
    $payload['type_details'] = [
        'first_run_distance_km' => 5,
        'bike_distance_km' => 20,
    ];

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), $payload)
        ->assertSessionHasErrors('type_details.second_run_distance_km');

    expect($event->categories()->count())->toBe(0);
});

test('category schedule must stay inside the overall event schedule', function (string $scheduledStartTime) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);

    $this
        ->actingAs($admin)
        ->from(route('admin.categories.create', ['event_id' => $event->id]))
        ->post(route('admin.categories.store'), scheduledCategoryPayload($event, $scheduledStartTime))
        ->assertRedirect(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertSessionHasErrors('scheduled_start_time');

    expect($event->categories()->count())->toBe(0);
})->with(['before event' => '05:59', 'after event' => '12:01']);

test('category cutoff end must be after its gun start and inside the event schedule', function (string $scheduledEndTime) {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.store'), scheduledCategoryPayload($event, '07:30', $scheduledEndTime))
        ->assertSessionHasErrors('scheduled_end_time');

    expect($event->categories()->count())->toBe(0);
})->with(['equal to gun start' => '07:30', 'before gun start' => '07:29', 'after event' => '12:01']);

test('category schedule is exposed to the mobile API resource', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'scheduled_start_time' => '07:30',
        'scheduled_end_time' => '10:00',
        'status' => 'open',
    ]);

    $payload = (new CategoryResource($category))->toArray(Request::create('/api/events'));

    expect($payload['scheduled_start_time'])->toBe('07:30')
        ->and($payload['scheduled_end_time'])->toBe('10:00')
        ->and($payload['scheduled_start_date'])->toBe($event->event_date->format('Y-m-d'))
        ->and($payload['scheduled_end_date'])->toBe($event->event_date->format('Y-m-d'))
        ->and($payload['scheduled_start_at'])->toContain('T07:30:00')
        ->and($payload['scheduled_end_at'])->toContain('T10:00:00');
});

test('category forms display the gun start and end fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Gun Start Date')
        ->assertSee('name="scheduled_start_date"', false)
        ->assertSee('Gun Start Time')
        ->assertSee('name="scheduled_start_time"', false)
        ->assertSee('End Date')
        ->assertSee('End Time')
        ->assertSee('name="scheduled_end_date"', false)
        ->assertSee('name="scheduled_end_time"', false);
});
