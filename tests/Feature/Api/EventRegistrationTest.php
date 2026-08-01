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
        ->assertJsonPath('message', 'You are already registered for this event.');
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

test('mobile registration requires a medical certificate for 50km and above', function () {
    $token = 'ultra-compliance-token';
    mobileUserWithToken($token);
    [$event, $category] = openEventWithCategory();
    $category->update([
        'name' => '50K Ultra',
        'distance_km' => 50,
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
