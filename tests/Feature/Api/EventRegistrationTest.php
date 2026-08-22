<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function mobileUserWithToken(string $token = 'mobile-token'): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
}

function openEventWithCategory(): array
{
    $event = Event::create([
        'title' => 'City Fun Run',
        'slug' => 'city-fun-run',
        'description' => 'A complete public event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);

    $category = Category::create([
        'event_id' => $event->id,
        'name' => '5K',
        'distance_km' => 5,
        'slot_limit' => 25,
        'status' => 'open',
        'scheduled_start_time' => '06:00',
        'scheduled_end_time' => '08:00',
    ]);

    return [$event, $category];
}

test('mobile event list hides draft events', function () {
    Event::create([
        'title' => 'Incomplete Draft Run',
        'slug' => 'incomplete-draft-run',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'status' => 'draft',
    ]);

    $this
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('mobile event list shows ready upcoming events with join state', function () {
    openEventWithCategory();

    $this
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'upcoming')
        ->assertJsonPath('data.0.can_register', true)
        ->assertJsonPath('data.0.registration_deadline_passed', false);
});

test('a rejected registration can be submitted again for review', function () {
    $token = 'mobile-token';
    $user = mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();

    $registration = Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'medical_conditions' => 'Old note',
        'status' => 'rejected',
        'rejection_reason' => 'Missing emergency contact.',
        'registered_at' => now()->subDay(),
    ]);

    $response = $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'L',
            'medical_conditions' => 'No known conditions',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
            'waiver_name' => 'Mobile Runner',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Registration submitted again for review.')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.rejection_reason', null);

    expect($registration->fresh())
        ->status->toBe('pending')
        ->rejection_reason->toBeNull()
        ->shirt_size->toBe('L')
        ->medical_conditions->toBe('No known conditions');
});

test('a non rejected registration cannot be duplicated', function () {
    $token = 'mobile-token';
    $user = mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'L',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You are already registered for this category.');
});

test('a participant can register for multiple non conflicting categories in one event', function () {
    $token = 'multiple-category-token';
    $user = mobileUserWithToken($token);
    [$event, $firstCategory] = openEventWithCategory();
    $secondCategory = Category::create([
        'event_id' => $event->id,
        'name' => '10K Afternoon',
        'distance_km' => 10,
        'slot_limit' => 25,
        'status' => 'open',
        'scheduled_start_time' => '08:30',
        'scheduled_end_time' => '11:00',
    ]);

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $firstCategory->id,
        'shirt_size' => 'M',
        'status' => 'approved',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$secondCategory->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.category.id', $secondCategory->id);

    expect($user->registrations()->where('event_id', $event->id)->count())->toBe(2);

    $this
        ->withToken($token)
        ->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.participants_count', 1)
        ->assertJsonPath('data.registration_entries_count', 2);
});

test('categories at the same clock time on different event days do not conflict', function () {
    $token = 'different-day-category-token';
    $user = mobileUserWithToken($token);
    [$event, $firstCategory] = openEventWithCategory();
    $secondDay = $event->event_date->copy()->addDay();
    $event->update(['event_end_date' => $secondDay]);
    $firstCategory->update([
        'scheduled_start_date' => $event->event_date,
        'scheduled_end_date' => $event->event_date,
    ]);
    $secondCategory = Category::create([
        'event_id' => $event->id,
        'name' => 'Day Two 5K',
        'distance_km' => 5,
        'slot_limit' => 25,
        'status' => 'open',
        'scheduled_start_date' => $secondDay,
        'scheduled_start_time' => '06:00',
        'scheduled_end_date' => $secondDay,
        'scheduled_end_time' => '08:00',
    ]);

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $firstCategory->id,
        'status' => 'approved',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$secondCategory->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertCreated();

    expect($user->registrations()->where('event_id', $event->id)->count())->toBe(2);
});

test('overlapping categories and gaps shorter than thirty minutes are rejected', function (string $startTime, string $endTime) {
    $token = 'conflicting-category-token-'.str_replace(':', '', $startTime);
    $user = mobileUserWithToken($token);
    [$event, $firstCategory] = openEventWithCategory();
    $conflictingCategory = Category::create([
        'event_id' => $event->id,
        'name' => 'Conflicting Category '.$startTime,
        'distance_km' => 10,
        'status' => 'open',
        'scheduled_start_time' => $startTime,
        'scheduled_end_time' => $endTime,
    ]);

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $firstCategory->id,
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$conflictingCategory->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('conflict_category_id', $firstCategory->id)
        ->assertJsonPath('safety_buffer_minutes', 30)
        ->assertJsonPath('message', "This category conflicts with {$firstCategory->name}. A 30-minute gap is required between categories.");

    expect($user->registrations()->where('event_id', $event->id)->count())->toBe(1);
})->with([
    'overlapping window' => ['07:00', '09:00'],
    'gap shorter than buffer' => ['08:29', '10:00'],
]);

test('a rejected category does not block a compatible registration', function () {
    $token = 'rejected-category-token';
    $user = mobileUserWithToken($token);
    [$event, $rejectedCategory] = openEventWithCategory();
    $nextCategory = Category::create([
        'event_id' => $event->id,
        'name' => '10K Morning',
        'distance_km' => 10,
        'status' => 'open',
        'scheduled_start_time' => '08:30',
        'scheduled_end_time' => '11:00',
    ]);

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $rejectedCategory->id,
        'status' => 'rejected',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$nextCategory->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertCreated();

    expect($user->registrations()->where('event_id', $event->id)->count())->toBe(2);
});

test('an incomplete category schedule blocks an additional registration safely', function () {
    $token = 'incomplete-category-schedule-token';
    $user = mobileUserWithToken($token);
    [$event, $firstCategory] = openEventWithCategory();
    $unscheduledCategory = Category::create([
        'event_id' => $event->id,
        'name' => 'Unscheduled Category',
        'distance_km' => 10,
        'status' => 'open',
    ]);

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $firstCategory->id,
        'status' => 'approved',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$unscheduledCategory->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This category does not have a complete gun start and cutoff/end schedule.');

    expect($user->registrations()->where('event_id', $event->id)->count())->toBe(1);
});

test('event payload exposes backward compatible and multi category registration state', function () {
    $token = 'registration-state-token';
    $user = mobileUserWithToken($token);
    [$event, $firstCategory] = openEventWithCategory();
    $secondCategory = Category::create([
        'event_id' => $event->id,
        'name' => '10K Afternoon',
        'distance_km' => 10,
        'status' => 'open',
        'scheduled_start_time' => '08:30',
        'scheduled_end_time' => '11:00',
    ]);

    $registration = Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $firstCategory->id,
        'status' => 'approved',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.is_registered', true)
        ->assertJsonPath('data.current_registration.id', $registration->id)
        ->assertJsonPath('data.registered_category_ids.0', $firstCategory->id)
        ->assertJsonPath('data.active_registered_category_ids.0', $firstCategory->id)
        ->assertJsonPath('data.category_registration_buffer_minutes', 30)
        ->assertJsonPath('data.category_registration_states.0.can_register', false)
        ->assertJsonPath('data.category_registration_states.1.category_id', $secondCategory->id)
        ->assertJsonPath('data.category_registration_states.1.can_register', true)
        ->assertJsonCount(1, 'data.current_registrations');
});

test('mobile registration requires waiver and first aid confirmation', function () {
    $token = 'missing-compliance-token';
    mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'M',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_aid_kit_confirmed', 'waiver_accepted']);
});

test('mobile registration requires a medical certificate when the category requires it', function () {
    $token = 'ultra-compliance-token';
    mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();
    $category->update([
        'name' => '50K Ultra',
        'distance_km' => 50,
        'requires_medical_certificate' => true,
    ]);

    $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'M',
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['medical_certificate']);
});

test('mobile registration stores medical certificate and waiver details', function () {
    Storage::fake('public');

    $token = 'cert-compliance-token';
    mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();
    $category->update([
        'name' => '50K Ultra',
        'distance_km' => 50,
        'requires_medical_certificate' => true,
    ]);

    $response = $this
        ->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", [
            'shirt_size' => 'M',
            'medical_certificate' => UploadedFile::fake()->create('fit-to-run.pdf', 128, 'application/pdf'),
            'first_aid_kit_confirmed' => true,
            'waiver_accepted' => true,
            'waiver_name' => 'Mobile Runner',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.requires_medical_certificate', true)
        ->assertJsonPath('data.first_aid_kit_confirmed', true)
        ->assertJsonPath('data.waiver_accepted', true)
        ->assertJsonPath('data.waiver_name', 'Mobile Runner');

    $registration = Registration::firstWhere('event_id', $event->id);

    expect($registration->medical_certificate_path)->not->toBeNull();
    expect($registration->medical_certificate_submitted_at)->not->toBeNull();
    expect($registration->waiver_accepted_at)->not->toBeNull();

    Storage::disk('public')->assertExists($registration->medical_certificate_path);
});

test('event payload includes the rejection reason for the current user', function () {
    $token = 'mobile-token';
    $user = mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();

    Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'shirt_size' => 'M',
        'status' => 'rejected',
        'rejection_reason' => 'Category details need correction.',
        'registered_at' => now(),
    ]);

    $this
        ->withToken($token)
        ->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.is_registered', true)
        ->assertJsonPath('data.registration_status', 'rejected')
        ->assertJsonPath('data.registration_rejection_reason', 'Category details need correction.')
        ->assertJsonPath('data.can_register', true)
        ->assertJsonPath('data.current_registration.rejection_reason', 'Category details need correction.');
});
