<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

function participantExportEvent(User $manager, string $title): Event
{
    return Event::create([
        'title' => $title,
        'slug' => str($title)->slug().'-'.uniqid(),
        'description' => 'Participant export test event.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeeks(2)->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager->id,
    ]);
}

function participantExportCategory(Event $event, string $name): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => $name,
        'distance_km' => 10,
        'status' => 'open',
    ]);
}

function participantExportRegistration(User $runner, Event $event, Category $category, array $overrides = []): Registration
{
    return Registration::create(array_merge([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => (string) fake()->unique()->numberBetween(100, 999),
        'status' => 'approved',
        'payment_status' => 'paid',
        'payment_amount_cents' => 50000,
        'payment_currency' => 'PHP',
        'registered_at' => now(),
    ], $overrides));
}

test('super admin can export participants using event category and status filters', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = participantExportEvent($admin, 'Manila Marathon');
    $tenKilometer = participantExportCategory($event, '10K Open');
    $fiveKilometer = participantExportCategory($event, '5K Open');
    $includedRunner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => '=SUM(1+1)',
        'email' => 'included@example.test',
        'phone' => '09171234567',
    ]);
    $excludedRunner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Excluded Runner',
        'email' => 'excluded@example.test',
    ]);

    participantExportRegistration($includedRunner, $event, $tenKilometer);
    participantExportRegistration($excludedRunner, $event, $fiveKilometer, ['status' => 'pending']);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.participants.export', [
            'event_id' => $event->id,
            'category_id' => $tenKilometer->id,
            'status' => 'approved',
        ]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('x-content-type-options', 'nosniff');

    $csv = $response->streamedContent();

    expect($csv)
        ->toStartWith("\xEF\xBB\xBF")
        ->toContain('Participant Name')
        ->toContain("'=SUM(1+1)")
        ->toContain('included@example.test')
        ->toContain('Manila Marathon')
        ->toContain('10K Open')
        ->not->toContain('excluded@example.test');
});

test('event manager export is limited to assigned events', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherManager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $assignedEvent = participantExportEvent($manager, 'Assigned Race');
    $otherEvent = participantExportEvent($otherManager, 'Private Race');
    $assignedCategory = participantExportCategory($assignedEvent, 'Assigned Category');
    $otherCategory = participantExportCategory($otherEvent, 'Private Category');
    $assignedRunner = User::factory()->create(['role' => User::ROLE_RUNNER, 'email' => 'assigned@example.test']);
    $privateRunner = User::factory()->create(['role' => User::ROLE_RUNNER, 'email' => 'private@example.test']);

    participantExportRegistration($assignedRunner, $assignedEvent, $assignedCategory);
    participantExportRegistration($privateRunner, $otherEvent, $otherCategory);

    $response = $this
        ->actingAs($manager)
        ->get(route('admin.participants.export'));

    $response->assertOk();

    expect($response->streamedContent())
        ->toContain('assigned@example.test')
        ->not->toContain('private@example.test');
});

test('runner cannot export admin participant data', function () {
    $runner = User::factory()->create(['role' => User::ROLE_RUNNER]);

    $this
        ->actingAs($runner)
        ->get(route('admin.participants.export'))
        ->assertForbidden();
});
