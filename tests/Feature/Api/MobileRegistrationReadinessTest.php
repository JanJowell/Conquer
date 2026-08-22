<?php

use App\Models\Category;
use App\Models\EBadge;
use App\Models\Event;
use App\Models\IssuedEBadge;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;

function readinessRunner(): array
{
    $plainToken = 'readiness-token-'.uniqid();
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$runner, $plainToken];
}

function readinessEvent(string $eventType = 'Trail Run'): Event
{
    return Event::create([
        'title' => 'Readiness Event '.uniqid(),
        'slug' => 'readiness-event-'.uniqid(),
        'description' => 'Tests the participant readiness API.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'event_end_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Racetech',
        'interest_type' => $eventType,
        'type_details' => $eventType === 'Trail Run' ? ['cutoff_time' => '6 hours'] : null,
    ]);
}

function readinessCategory(Event $event, array $overrides = []): Category
{
    return Category::create(array_merge([
        'event_id' => $event->id,
        'name' => '50K Open',
        'distance_km' => 50,
        'type_details' => ['mandatory_gear' => 'Hydration vest, whistle, and headlamp'],
        'qualification_notes' => "Must be at least 18 years old.\nPrevious trail experience is required.",
        'scheduled_start_time' => '06:30',
        'scheduled_end_time' => '11:30',
        'status' => 'open',
    ], $overrides));
}

function readinessRegistration(User $runner, Event $event, Category $category, array $overrides = []): Registration
{
    return Registration::create(array_merge([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'status' => 'pending',
        'payment_required' => false,
        'payment_status' => 'waived',
        'waiver_accepted' => true,
        'waiver_accepted_at' => now(),
        'first_aid_kit_confirmed' => true,
        'registered_at' => now(),
    ], $overrides));
}

test('my registrations exposes payment requirements gear qualification notes and schedule', function () {
    [$runner, $token] = readinessRunner();
    $event = readinessEvent();
    $category = readinessCategory($event);
    readinessRegistration($runner, $event, $category, [
        'payment_required' => true,
        'payment_status' => 'unpaid',
        'payment_amount_cents' => 150000,
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.readiness.overall_status', 'action_required')
        ->assertJsonPath('data.0.readiness.next_action.key', 'complete_payment')
        ->assertJsonPath('data.0.readiness.steps.payment.status', 'action_required')
        ->assertJsonPath('data.0.readiness.steps.medical_certificate.required', true)
        ->assertJsonPath('data.0.readiness.requirements.gear_label', 'Mandatory Gear')
        ->assertJsonPath('data.0.readiness.requirements.gear', 'Hydration vest, whistle, and headlamp')
        ->assertJsonPath('data.0.readiness.requirements.qualification_notes', "Must be at least 18 years old.\nPrevious trail experience is required.")
        ->assertJsonPath('data.0.readiness.schedule.scheduled_start_time', '06:30')
        ->assertJsonPath('data.0.readiness.schedule.scheduled_end_time', '11:30');

    $this
        ->withToken($token)
        ->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.current_registration.readiness.next_action.key', 'complete_payment')
        ->assertJsonPath('data.current_registrations.0.readiness.steps.medical_certificate.required', true);
});

test('readiness distinguishes waiting payment from a missing medical certificate', function () {
    [$runner, $token] = readinessRunner();
    $event = readinessEvent();
    $waitingCategory = readinessCategory($event, ['name' => '50K Waiting']);
    $medicalCategory = readinessCategory($event, ['name' => '50K Medical']);
    readinessRegistration($runner, $event, $waitingCategory, [
        'payment_required' => true,
        'payment_status' => 'submitted',
    ]);
    readinessRegistration($runner, $event, $medicalCategory);

    $response = $this
        ->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk();

    $payload = collect($response->json('data'))->keyBy('category.id');

    expect(data_get($payload[$waitingCategory->id], 'readiness.overall_status'))->toBe('awaiting_payment_confirmation')
        ->and(data_get($payload[$waitingCategory->id], 'readiness.next_action.key'))->toBe('await_payment_confirmation')
        ->and(data_get($payload[$medicalCategory->id], 'readiness.next_action.key'))->toBe('submit_medical_certificate');
});

test('an approved complete registration is ready for event day check in', function () {
    [$runner, $token] = readinessRunner();
    $event = readinessEvent('Cycling');
    $category = readinessCategory($event, [
        'name' => '10K Open',
        'distance_km' => 10,
        'type_details' => null,
        'qualification_notes' => null,
    ]);
    readinessRegistration($runner, $event, $category, [
        'status' => 'approved',
        'bib_number' => '101',
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.readiness.overall_status', 'ready_for_event_day')
        ->assertJsonPath('data.0.readiness.is_ready_for_event_day', true)
        ->assertJsonPath('data.0.readiness.next_action.key', 'event_day_check_in')
        ->assertJsonPath('data.0.readiness.steps.approval.completed', true)
        ->assertJsonPath('data.0.readiness.steps.payment.status', 'not_required')
        ->assertJsonPath('data.0.readiness.steps.bib.completed', true)
        ->assertJsonPath('data.0.readiness.steps.medical_certificate.status', 'not_required');
});

test('completed registration exposes its result and issued e badges', function () {
    [$runner, $token] = readinessRunner();
    $event = readinessEvent('Marathon');
    $category = readinessCategory($event, [
        'name' => '10K Open',
        'distance_km' => 10,
        'type_details' => null,
    ]);
    $registration = readinessRegistration($runner, $event, $category, [
        'status' => 'completed',
        'bib_number' => '202',
        'kit_released_at' => now(),
    ]);
    $result = RaceResult::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'finish_time' => '00:48:15',
        'rank_overall' => 3,
        'rank_category' => 1,
    ]);
    $badge = EBadge::create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'title' => 'Category Champion',
        'image_path' => 'badges/category-champion.png',
        'is_active' => true,
    ]);
    IssuedEBadge::create([
        'e_badge_id' => $badge->id,
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'issued_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.readiness.overall_status', 'completed')
        ->assertJsonPath('data.0.readiness.next_action.key', 'view_results')
        ->assertJsonPath('data.0.readiness.result.id', $result->id)
        ->assertJsonPath('data.0.readiness.result.finish_time', '00:48:15')
        ->assertJsonPath('data.0.readiness.e_badges.available', true)
        ->assertJsonPath('data.0.readiness.e_badges.count', 1)
        ->assertJsonPath('data.0.readiness.e_badges.items.0.title', 'Category Champion');
});

test('rejected registration readiness preserves the backend reason', function () {
    [$runner, $token] = readinessRunner();
    $event = readinessEvent('Cycling');
    $category = readinessCategory($event, ['distance_km' => 10, 'type_details' => null]);
    readinessRegistration($runner, $event, $category, [
        'status' => 'rejected',
        'rejection_reason' => 'Age requirement was not met.',
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/my-registrations')
        ->assertOk()
        ->assertJsonPath('data.0.readiness.overall_status', 'rejected')
        ->assertJsonPath('data.0.readiness.next_action.key', 'registration_rejected')
        ->assertJsonPath('data.0.readiness.next_action.message', 'Age requirement was not met.')
        ->assertJsonPath('data.0.readiness.steps.approval.status', 'failed');
});
