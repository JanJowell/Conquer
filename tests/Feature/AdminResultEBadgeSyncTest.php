<?php

use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\IssuedEBadge;
use App\Models\Registration;
use App\Models\User;
use App\Services\EBadgeAutoIssuer;
use App\Services\EBadgeNotificationService;

function resultBadgeAdmin(): User
{
    return User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
}

function resultBadgeRunner(string $name): User
{
    return User::factory()->create([
        'name' => $name,
        'role' => User::ROLE_RUNNER,
    ]);
}

function resultBadgeEvent(): Event
{
    return Event::create([
        'title' => 'Race Day Sync Test',
        'slug' => 'race-day-sync-test-'.uniqid(),
        'description' => 'A test race.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addWeek()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->toDateString(),
        'status' => 'draft',
        'organized_by' => 'Racetech',
        'interest_type' => config('conquer.event_interest_types.0'),
        'banner_image' => 'events/banners/sample.jpg',
    ]);
}

function resultBadgeCategory(Event $event): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '5K Open',
        'distance_km' => 5,
        'status' => 'open',
    ]);
}

function checkedInRegistration(Event $event, Category $category, User $runner, string $bib): Registration
{
    return Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => $bib,
        'status' => 'checked_in',
        'registered_at' => now(),
    ]);
}

function bindQuietEBadgeNotifications(): void
{
    $notifications = Mockery::mock(EBadgeNotificationService::class);
    $notifications->shouldReceive('notifyIssued')->zeroOrMoreTimes();

    app()->instance(EBadgeNotificationService::class, $notifications);
}

test('saving and updating results keeps automatic e-badges in sync with recalculated ranks', function () {
    bindQuietEBadgeNotifications();

    $admin = resultBadgeAdmin();
    $event = resultBadgeEvent();
    $category = resultBadgeCategory($event);
    $firstRunner = resultBadgeRunner('First Runner');
    $secondRunner = resultBadgeRunner('Second Runner');
    $firstRegistration = checkedInRegistration($event, $category, $firstRunner, '101');
    $secondRegistration = checkedInRegistration($event, $category, $secondRunner, '102');

    $firstPlaceBadge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Category Champion',
        'auto_issue_rule' => EBadgeAutoIssuer::FIRST_CATEGORY,
        'is_active' => true,
    ]);

    $secondPlaceBadge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Category Second Place',
        'auto_issue_rule' => EBadgeAutoIssuer::SECOND_CATEGORY,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.results.store'), [
            'registration_id' => $firstRegistration->id,
            'finish_time' => '25:00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($firstPlaceBadge->issuedBadges()->where('registration_id', $firstRegistration->id)->exists())->toBeTrue();

    $this
        ->actingAs($admin)
        ->post(route('admin.results.store'), [
            'registration_id' => $secondRegistration->id,
            'finish_time' => '24:00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstRegistration->refresh();
    $secondRegistration->refresh();

    expect($secondRegistration->raceResult->fresh()->rank_category)->toBe(1)
        ->and($firstRegistration->raceResult->fresh()->rank_category)->toBe(2)
        ->and($firstPlaceBadge->issuedBadges()->where('registration_id', $secondRegistration->id)->exists())->toBeTrue()
        ->and($firstPlaceBadge->issuedBadges()->where('registration_id', $firstRegistration->id)->exists())->toBeFalse()
        ->and($secondPlaceBadge->issuedBadges()->where('registration_id', $firstRegistration->id)->exists())->toBeTrue();
});
