<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;

function categoryRaceEvent(User $manager, Carbon $scheduledStart): Event
{
    return Event::create([
        'title' => 'Category Start Test '.uniqid(),
        'slug' => 'category-start-test-'.uniqid(),
        'description' => 'Race-day category timing test.',
        'venue' => 'Bacoor City',
        'event_date' => $scheduledStart->toDateString(),
        'start_time' => $scheduledStart->format('H:i'),
        'registration_deadline' => $scheduledStart->copy()->subDay()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ]);
}

function categoryRaceCategory(Event $event, array $overrides = []): Category
{
    return Category::create(array_merge([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ], $overrides));
}

test('starting a category records one authoritative server timestamp and administrator', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-08-15 06:15:30', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($admin, $raceNow->copy()->subMinutes(15));
    $category = categoryRaceCategory($event);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('success');

    $category->refresh();

    expect($category->started_at->timestamp)->toBe($raceNow->timestamp)
        ->and($category->started_by_user_id)->toBe($admin->id);

    $this->travelTo($raceNow->copy()->addMinutes(5));

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($category->fresh()->started_at->timestamp)->toBe($raceNow->timestamp);
});

test('a category cannot start before the general event schedule or while in draft', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-08-15 05:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($admin, $raceNow->copy()->addHour());
    $category = categoryRaceCategory($event);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($category->fresh()->started_at)->toBeNull();

    $category->update(['status' => 'draft']);
    $this->travelTo($raceNow->copy()->addHours(2));

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($category->fresh()->started_at)->toBeNull();
});

test('event managers cannot start categories assigned to another manager', function () {
    $assignedManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $raceNow = Carbon::parse('2026-08-15 07:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($assignedManager, $raceNow->copy()->subHour());
    $category = categoryRaceCategory($event);

    $this
        ->actingAs($otherManager)
        ->post(route('admin.categories.start', $category))
        ->assertForbidden();

    expect($category->fresh()->started_at)->toBeNull();
});

test('finish button requires a category start and calculates from that exact timestamp', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $raceNow = Carbon::parse('2026-08-15 06:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($admin, $raceNow->copy()->subMinutes(30));
    $category = categoryRaceCategory($event);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => '101',
        'status' => 'checked_in',
        'registered_at' => $raceNow->copy()->subDay(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.results.store'), [
            'registration_id' => $registration->id,
            'finish_now' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($registration->raceResult()->exists())->toBeFalse();

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertSessionHas('success');

    $this->travelTo($raceNow->copy()->addSeconds(3723));

    $this
        ->actingAs($admin)
        ->post(route('admin.results.store'), [
            'registration_id' => $registration->id,
            'finish_now' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($registration->raceResult()->first()->finish_time)->toBe('01:02:03');
});

test('results page presents category start controls and disables finish before start', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $raceNow = Carbon::parse('2026-08-15 06:00:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($admin, $raceNow->copy()->subMinutes(30));
    $category = categoryRaceCategory($event);
    Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => '102',
        'status' => 'checked_in',
        'registered_at' => $raceNow->copy()->subDay(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.results.index', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Category Starts')
        ->assertSee('Start Category')
        ->assertSee('Category not started')
        ->assertSee('disabled', false);
});
