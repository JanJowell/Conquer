<?php

use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\IssuedEBadge;
use App\Models\Registration;
use App\Models\User;
use App\Services\EBadgeAutoIssuer;

function mobileAchievementRunner(): array
{
    $plainToken = 'mobile-achievement-token-'.uniqid();

    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$runner, $plainToken];
}

function mobileAchievementEvent(): Event
{
    return Event::create([
        'title' => 'Mobile Achievement Race',
        'slug' => 'mobile-achievement-race-'.uniqid(),
        'venue' => 'Bacoor City',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => 'draft',
    ]);
}

function mobileAchievementCategory(Event $event): Category
{
    return Category::create([
        'event_id' => $event->id,
        'name' => '10K Open',
        'distance_km' => 10,
        'status' => 'open',
    ]);
}

test('mobile achievements API exposes new e-badge auto issue rule labels', function () {
    [$runner, $plainToken] = mobileAchievementRunner();
    $event = mobileAchievementEvent();
    $category = mobileAchievementCategory($event);

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => '201',
        'status' => 'completed',
        'registered_at' => now(),
    ]);

    $badge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Overall Champion',
        'description' => 'Awarded to the fastest runner overall.',
        'auto_issue_rule' => EBadgeAutoIssuer::FIRST_OVERALL,
        'is_active' => true,
    ]);

    IssuedEBadge::create([
        'e_badge_id' => $badge->id,
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'issued_by' => null,
        'issued_at' => now(),
        'notes' => 'Automatically issued',
    ]);

    $this
        ->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/achievements')
        ->assertOk()
        ->assertJsonPath('data.0.auto_issue_rule', EBadgeAutoIssuer::FIRST_OVERALL)
        ->assertJsonPath('data.0.auto_issue_rule_label', '1st place overall')
        ->assertJsonPath('data.0.criteria', '1st place overall')
        ->assertJsonPath('data.0.status', 'unlocked')
        ->assertJsonPath('issued_badges.0.auto_issue_rule', EBadgeAutoIssuer::FIRST_OVERALL)
        ->assertJsonPath('issued_badges.0.auto_issue_rule_label', '1st place overall');
});
