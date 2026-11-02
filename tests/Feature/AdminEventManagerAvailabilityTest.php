<?php

use App\Models\Event;
use App\Models\User;

function managerAvailabilityEvent(User $manager, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Existing Managed Event '.uniqid(),
        'slug' => 'existing-managed-event-'.uniqid(),
        'description' => 'An existing manager assignment.',
        'venue' => 'Bacoor City',
        'event_date' => '2030-06-10',
        'event_end_date' => '2030-06-10',
        'start_time' => '07:00',
        'end_time' => '10:00',
        'registration_deadline' => '2030-06-01',
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ], $overrides));
}

function managerAvailabilityPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'New Managed Event '.uniqid(),
        'description' => 'A proposed manager assignment.',
        'venue' => 'Imus City',
        'event_date' => '2030-06-10',
        'event_end_date' => '2030-06-10',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'registration_deadline' => '2030-06-01',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
    ], $overrides);
}

test('event manager cannot assign a manager to overlapping event times', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    $existing = managerAvailabilityEvent($manager, ['title' => 'Morning Marathon']);

    $response = $this->actingAs($admin)
        ->post(route('admin.events.store'), managerAvailabilityPayload([
            'manager_id' => $manager->id,
        ]))
        ->assertSessionHasErrors('manager_id');

    $message = $response->getSession()->get('errors')->first('manager_id');

    expect($message)->toContain($existing->title)
        ->and($message)->toContain('unavailable')
        ->and(Event::where('title', 'like', 'New Managed Event%')->exists())->toBeFalse();
});

test('back to back events do not create a manager conflict', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    managerAvailabilityEvent($manager);

    $title = 'Back to Back Event '.uniqid();

    $this->actingAs($admin)
        ->post(route('admin.events.store'), managerAvailabilityPayload([
            'title' => $title,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'manager_id' => $manager->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Event::where('title', $title)->value('manager_id'))->toBe($manager->id);
});

test('multi day event overlap blocks an assigned manager', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    managerAvailabilityEvent($manager, [
        'event_date' => '2030-06-09',
        'event_end_date' => '2030-06-11',
        'start_time' => '08:00',
        'end_time' => '18:00',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), managerAvailabilityPayload([
            'manager_id' => $manager->id,
        ]))
        ->assertSessionHasErrors('manager_id');
});

test('event manager cannot create a conflicting event for themselves', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    managerAvailabilityEvent($manager);

    $this->actingAs($manager)
        ->post(route('admin.events.store'), managerAvailabilityPayload([
            'manager_id' => null,
        ]))
        ->assertSessionHasErrors('manager_id');
});

test('editing an event does not conflict with its own schedule', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    $event = managerAvailabilityEvent($manager);

    $this->actingAs($admin)
        ->put(route('admin.events.update', $event), managerAvailabilityPayload([
            'title' => $event->title,
            'manager_id' => $manager->id,
            'start_time' => '07:00',
            'end_time' => '10:00',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.events.show', $event));
});

test('a manager cannot edit or reassign another managers event', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    managerAvailabilityEvent($manager, ['title' => 'Manager Busy Event']);
    $event = managerAvailabilityEvent($otherManager, [
        'title' => 'Event Being Reassigned',
        'event_date' => '2030-06-12',
        'event_end_date' => '2030-06-12',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.events.update', $event), managerAvailabilityPayload([
            'title' => $event->title,
            'manager_id' => $manager->id,
        ]))
        ->assertForbidden();

    expect($event->fresh()->manager_id)->toBe($otherManager->id)
        ->and($event->fresh()->event_date->format('Y-m-d'))->toBe('2030-06-12');
});

test('date overlap is treated conservatively when event times are incomplete', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = $admin;
    managerAvailabilityEvent($manager, [
        'start_time' => null,
        'end_time' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.events.store'), managerAvailabilityPayload([
            'manager_id' => $manager->id,
            'start_time' => null,
            'end_time' => null,
        ]))
        ->assertSessionHasErrors('manager_id');
});

test('event form includes only the current managers schedule and no reassignment selector', function () {
    $admin = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $manager = User::factory()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'name' => 'Available Test Manager',
    ]);
    managerAvailabilityEvent($manager, ['title' => 'Other Manager Private Schedule']);
    managerAvailabilityEvent($admin, ['title' => 'Own Schedule Conflict']);

    $this->actingAs($admin)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertDontSee('name="manager_id"', false)
        ->assertSee('Own Schedule Conflict')
        ->assertDontSee('Other Manager Private Schedule');
});
