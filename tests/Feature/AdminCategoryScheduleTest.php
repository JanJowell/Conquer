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
        ->and($payload['scheduled_end_time'])->toBe('10:00');
});

test('category forms display the scheduled gun start and cutoff end fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = scheduledCategoryEvent($admin);

    $this
        ->actingAs($admin)
        ->get(route('admin.categories.create', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Scheduled Gun Start')
        ->assertSee('name="scheduled_start_time"', false)
        ->assertSee('Category Cutoff/End Time')
        ->assertSee('name="scheduled_end_time"', false);
});
