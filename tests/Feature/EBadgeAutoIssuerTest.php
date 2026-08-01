<?php

use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\EBadgeAutoIssuer;
use App\Services\EBadgeNotificationService;

function eBadgeEvent(): Event
{
    return Event::create([
        'title' => 'City Championship',
        'slug' => 'city-championship-'.uniqid(),
        'venue' => 'Bacoor City',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

function eBadgeCategory(Event $event): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);
}

function completedRegistrationWithRank(Event $event, Category $category, int $overallRank, int $categoryRank): Registration
{
    $user = User::factory()->create(['role' => User::ROLE_RUNNER]);

    $registration = Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => (string) (1000 + $overallRank),
        'status' => 'completed',
        'registered_at' => now(),
    ]);

    RaceResult::create([
        'registration_id' => $registration->id,
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'finish_time' => '00:4'.$overallRank.':00',
        'rank_overall' => $overallRank,
        'rank_category' => $categoryRank,
    ]);

    return $registration;
}

function autoIssuer(): EBadgeAutoIssuer
{
    $notifications = Mockery::mock(EBadgeNotificationService::class);
    $notifications->shouldReceive('notifyIssued')->zeroOrMoreTimes();

    return new EBadgeAutoIssuer($notifications);
}

test('precise overall auto issue rules match only the selected podium rank', function () {
    $event = eBadgeEvent();
    $category = eBadgeCategory($event);
    $first = completedRegistrationWithRank($event, $category, 1, 1);
    $second = completedRegistrationWithRank($event, $category, 2, 2);

    $firstBadge = EBadge::create([
        'event_id' => $event->id,
        'title' => 'Overall Champion',
        'auto_issue_rule' => EBadgeAutoIssuer::FIRST_OVERALL,
        'is_active' => true,
    ]);

    $secondBadge = EBadge::create([
        'event_id' => $event->id,
        'title' => 'Overall Second Place',
        'auto_issue_rule' => EBadgeAutoIssuer::SECOND_OVERALL,
        'is_active' => true,
    ]);

    autoIssuer()->issueForCompletedRegistration($first);
    autoIssuer()->issueForCompletedRegistration($second);

    expect($firstBadge->issuedBadges()->where('registration_id', $first->id)->exists())->toBeTrue()
        ->and($firstBadge->issuedBadges()->where('registration_id', $second->id)->exists())->toBeFalse()
        ->and($secondBadge->issuedBadges()->where('registration_id', $second->id)->exists())->toBeTrue()
        ->and($secondBadge->issuedBadges()->where('registration_id', $first->id)->exists())->toBeFalse();
});
test('top three and precise category rules can be used together', function () {
    $event = eBadgeEvent();
    $category = eBadgeCategory($event);
    $third = completedRegistrationWithRank($event, $category, 6, 3);

    $thirdCategoryBadge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Category Third Place',
        'auto_issue_rule' => EBadgeAutoIssuer::THIRD_CATEGORY,
        'is_active' => true,
    ]);

    $topThreeCategoryBadge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Category Podium',
        'auto_issue_rule' => EBadgeAutoIssuer::TOP_3_CATEGORY,
        'is_active' => true,
    ]);

    autoIssuer()->issueForCompletedRegistration($third);

    expect($thirdCategoryBadge->issuedBadges()->where('registration_id', $third->id)->exists())->toBeTrue()
        ->and($topThreeCategoryBadge->issuedBadges()->where('registration_id', $third->id)->exists())->toBeTrue();
});
