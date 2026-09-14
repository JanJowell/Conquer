<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\FinishScan;
use App\Models\RaceResult;
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

test('a category uses its own scheduled start instead of the general event start', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-08-15 06:15:00', config('app.timezone'));
    $this->travelTo($raceNow);
    $event = categoryRaceEvent($admin, $raceNow->copy()->subMinutes(15));
    $category = categoryRaceCategory($event, ['scheduled_start_time' => '06:30']);

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($category->fresh()->started_at)->toBeNull();

    $this->travelTo(Carbon::parse('2026-08-15 06:30:00', config('app.timezone')));

    $this
        ->actingAs($admin)
        ->post(route('admin.categories.start', $category))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($category->fresh()->started_at->format('H:i:s'))->toBe('06:30:00');
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

test('finish button records the same provisional server-timed finish as a QR scan', function () {
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

    expect($registration->finishScan()->exists())->toBeFalse()
        ->and($registration->raceResult()->exists())->toBeFalse();

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

    $finishScan = $registration->finishScan()->first();

    expect($finishScan)->not->toBeNull()
        ->and($finishScan->scanned_at->timestamp)->toBe($raceNow->copy()->addSeconds(3723)->timestamp)
        ->and($finishScan->elapsed_seconds)->toBe(3723)
        ->and($finishScan->elapsed_time)->toBe('01:02:03')
        ->and($finishScan->scanned_by_user_id)->toBe($admin->id)
        ->and($finishScan->status)->toBe(FinishScan::STATUS_PROVISIONAL)
        ->and($registration->fresh()->status)->toBe('checked_in')
        ->and(RaceResult::count())->toBe(0);

    $this
        ->actingAs($admin)
        ->get(route('admin.results.index', ['event_id' => $event->id]))
        ->assertOk()
        ->assertSee('Scanned · Review')
        ->assertSee('01:02:03')
        ->assertSee('Update')
        ->assertSee('Publish Results');

    $this
        ->actingAs($admin)
        ->post(route('admin.results.store'), [
            'registration_id' => $registration->id,
            'finish_now' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'This participant already has a recorded finish.');

    expect(FinishScan::where('registration_id', $registration->id)->count())->toBe(1)
        ->and(RaceResult::count())->toBe(0);
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

test('results display status follows the category schedule and publication state', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $raceNow = Carbon::parse('2026-08-15 08:00:00', config('app.timezone'));
    $event = categoryRaceEvent($admin, $raceNow->copy()->subHours(2));
    $category = categoryRaceCategory($event, [
        'scheduled_start_date' => '2026-08-15',
        'scheduled_start_time' => '06:00',
        'scheduled_end_date' => '2026-08-15',
        'scheduled_end_time' => '09:00',
    ]);

    expect($category->resultsDisplayStatus($raceNow, 0, 0))
        ->toBe(Category::RESULTS_STATUS_NOT_STARTED);

    $category->update(['started_at' => $raceNow->copy()->subHours(2)]);
    $category->refresh();

    expect($category->resultsDisplayStatus($raceNow, 0, 0))
        ->toBe(Category::RESULTS_STATUS_IN_PROGRESS)
        ->and($category->resultsDisplayStatus($raceNow->copy()->addHour(), 0, 0))
        ->toBe(Category::RESULTS_STATUS_ENDED)
        ->and($category->resultsDisplayStatus($raceNow->copy()->addHour(), 1, 1))
        ->toBe(Category::RESULTS_STATUS_PENDING)
        ->and($category->resultsDisplayStatus($raceNow->copy()->addHour(), 0, 1))
        ->toBe(Category::RESULTS_STATUS_COMPLETED);
});

test('results display status supports overnight and multi-day category schedules', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $eventStart = Carbon::parse('2026-08-15 23:00:00', config('app.timezone'));
    $event = categoryRaceEvent($admin, $eventStart);
    $event->update([
        'event_end_date' => '2026-08-16',
        'end_time' => '03:00',
    ]);
    $category = categoryRaceCategory($event, [
        'scheduled_start_date' => '2026-08-15',
        'scheduled_start_time' => '23:00',
        'scheduled_end_date' => '2026-08-16',
        'scheduled_end_time' => '01:00',
        'started_at' => $eventStart,
    ]);

    expect($category->resultsDisplayStatus(
        Carbon::parse('2026-08-16 00:30:00', config('app.timezone')),
        0,
        0,
    ))->toBe(Category::RESULTS_STATUS_IN_PROGRESS)
        ->and($category->resultsDisplayStatus(
            Carbon::parse('2026-08-16 01:00:00', config('app.timezone')),
            0,
            0,
        ))->toBe(Category::RESULTS_STATUS_ENDED);
});
