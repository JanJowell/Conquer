<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\RegistrationFeedback;
use App\Models\User;

function feedbackTestRunner(array $overrides = []): array
{
    $token = 'feedback-token-'.uniqid();
    $runner = User::factory()->create(array_merge([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDays(30),
    ], $overrides));

    return [$runner, $token];
}

function feedbackTestEvent(?User $manager = null, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Feedback Event '.uniqid(),
        'slug' => 'feedback-event-'.uniqid(),
        'description' => 'Tests verified post-event feedback.',
        'venue' => 'Bacoor City',
        'event_date' => now()->subDay()->toDateString(),
        'event_end_date' => now()->subDay()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->subWeek()->toDateString(),
        'status' => 'completed',
        'organized_by' => 'Racetech',
        'interest_type' => 'Marathon',
        'manager_id' => $manager?->id,
    ], $overrides));
}

function feedbackTestRegistration(User $runner, Event $event, bool $withResult = true): Registration
{
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '10K Open '.uniqid(),
        'distance_km' => 10,
        'status' => 'open',
    ]);
    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => $withResult ? 'completed' : 'checked_in',
        'bib_number' => $withResult ? (string) fake()->unique()->numberBetween(1000, 9999) : null,
        'payment_required' => false,
        'payment_status' => 'waived',
        'waiver_accepted' => true,
        'first_aid_kit_confirmed' => true,
        'kit_released_at' => $withResult ? now()->subDay() : null,
        'registered_at' => now()->subMonth(),
    ]);

    if ($withResult) {
        RaceResult::create([
            'registration_id' => $registration->id,
            'user_id' => $runner->id,
            'event_id' => $event->id,
            'category_id' => $category->id,
            'finish_time' => '00:58:10',
            'rank_overall' => 5,
            'rank_category' => 2,
        ]);
    }

    return $registration;
}

function feedbackTestPayload(array $overrides = []): array
{
    return array_merge([
        'overall_rating' => 5,
        'organization_rating' => 4,
        'route_rating' => 5,
        'safety_rating' => 4,
        'experience_rating' => 5,
        'comment' => 'The event was organized well and the route was clearly marked.',
    ], $overrides);
}

test('feedback is unavailable until an official result exists', function () {
    [$runner, $token] = feedbackTestRunner();
    $registration = feedbackTestRegistration($runner, feedbackTestEvent(), false);

    $this->withToken($token)
        ->getJson("/api/registrations/{$registration->id}/feedback")
        ->assertOk()
        ->assertJsonPath('data', null)
        ->assertJsonPath('eligibility.has_result', false)
        ->assertJsonPath('eligibility.can_submit', false);

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload())
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Feedback is available after your official result has been recorded.');
});

test('participant submits one category-specific response and readiness exposes it', function () {
    [$runner, $token] = feedbackTestRunner();
    $registration = feedbackTestRegistration($runner, feedbackTestEvent());

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload())
        ->assertCreated()
        ->assertJsonPath('data.registration_id', $registration->id)
        ->assertJsonPath('data.overall_rating', 5)
        ->assertJsonPath('data.ratings.organization', 4)
        ->assertJsonPath('data.can_edit', true)
        ->assertJsonPath('data.edit_window_days', 7);

    expect(RegistrationFeedback::count())->toBe(1)
        ->and(RegistrationFeedback::first()->event_id)->toBe($registration->event_id)
        ->and(RegistrationFeedback::first()->category_id)->toBe($registration->category_id);

    $this->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.feedback.overall_rating', 5)
        ->assertJsonPath('data.0.readiness.steps.feedback.completed', true)
        ->assertJsonPath('data.0.readiness.feedback.has_submitted', true)
        ->assertJsonPath('data.0.readiness.next_action.key', 'view_results');

    $this->withToken($token)
        ->getJson('/api/my-results')
        ->assertOk()
        ->assertJsonPath('data.0.feedback_required', true)
        ->assertJsonPath('data.0.can_submit_feedback', false)
        ->assertJsonPath('data.0.feedback.overall_rating', 5);
});

test('overall rating is required and all supplied ratings must be between one and five', function () {
    [$runner, $token] = feedbackTestRunner();
    $registration = feedbackTestRegistration($runner, feedbackTestEvent());

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", [
            'organization_rating' => 6,
            'comment' => str_repeat('x', 2001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['overall_rating', 'organization_rating', 'comment']);
});

test('participant can update feedback within seven days without creating a duplicate', function () {
    [$runner, $token] = feedbackTestRunner();
    $registration = feedbackTestRegistration($runner, feedbackTestEvent());

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload())
        ->assertCreated();
    $submittedAt = RegistrationFeedback::firstOrFail()->submitted_at->toISOString();

    $this->travel(6)->days();

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload([
            'overall_rating' => 4,
            'comment' => 'Updated after thinking about the event.',
        ]))
        ->assertOk()
        ->assertJsonPath('data.overall_rating', 4);

    expect(RegistrationFeedback::count())->toBe(1)
        ->and(RegistrationFeedback::first()->submitted_at->toISOString())->toBe($submittedAt);
});

test('feedback becomes read only after the seven-day editing period', function () {
    [$runner, $token] = feedbackTestRunner();
    $registration = feedbackTestRegistration($runner, feedbackTestEvent());
    RegistrationFeedback::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $registration->event_id,
        'category_id' => $registration->category_id,
        'overall_rating' => 5,
        'submitted_at' => now()->subDays(8),
    ]);

    $this->withToken($token)
        ->getJson("/api/registrations/{$registration->id}/feedback")
        ->assertOk()
        ->assertJsonPath('data.can_edit', false)
        ->assertJsonPath('eligibility.can_edit', false);

    $this->withToken($token)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload(['overall_rating' => 1]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The seven-day feedback editing period has ended.');
});

test('participant cannot access another users registration feedback', function () {
    [$owner] = feedbackTestRunner();
    [, $otherToken] = feedbackTestRunner();
    $registration = feedbackTestRegistration($owner, feedbackTestEvent());

    $this->withToken($otherToken)
        ->getJson("/api/registrations/{$registration->id}/feedback")
        ->assertForbidden();
    $this->withToken($otherToken)
        ->putJson("/api/registrations/{$registration->id}/feedback", feedbackTestPayload())
        ->assertForbidden();
});

test('event manager feedback insights include only assigned event responses', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    [$runner] = feedbackTestRunner();
    $managedRegistration = feedbackTestRegistration($runner, feedbackTestEvent($manager));
    $otherRegistration = feedbackTestRegistration($runner, feedbackTestEvent());

    foreach ([[$managedRegistration, 'Managed feedback'], [$otherRegistration, 'Other feedback']] as [$registration, $comment]) {
        RegistrationFeedback::create([
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'event_id' => $registration->event_id,
            'category_id' => $registration->category_id,
            'overall_rating' => 5,
            'comment' => $comment,
            'submitted_at' => now(),
        ]);
    }

    $this->actingAs($manager)
        ->get(route('admin.feedback-insights'))
        ->assertOk()
        ->assertSee('Managed feedback')
        ->assertDontSee('Other feedback')
        ->assertSee('Feedback is linked to the participant')
        ->assertDontSee('name="overall_rating"', false);
});
