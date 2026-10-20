<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Support\ParticipantBirthdate;
use Illuminate\Support\Facades\Mail;

test('new mobile participants must provide a plausible past birthdate', function () {
    Mail::fake();

    $payload = fn (string $email, mixed $birthdate = null): array => array_filter([
        'name' => 'Birthdate Test Participant',
        'username' => (string) str($email)->before('@')->replace('.', '_'),
        'email' => $email,
        'birthdate' => $birthdate,
        'password' => 'SecureParticipant!2026',
        'password_confirmation' => 'SecureParticipant!2026',
    ], fn ($value) => $value !== null);

    $this->postJson('/api/register', $payload('missing.birthdate@example.test'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birthdate')
        ->assertJsonPath('errors.birthdate.0', 'Birthdate is required for participant accounts.');

    $this->postJson('/api/register', $payload('today.birthdate@example.test', today()->toDateString()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birthdate')
        ->assertJsonPath('errors.birthdate.0', 'Birthdate must be before today.');

    $this->postJson('/api/register', $payload('future.birthdate@example.test', today()->addDay()->toDateString()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birthdate');

    $this->postJson('/api/register', $payload('implausible.birthdate@example.test', today()->subYears(120)->subDay()->toDateString()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birthdate')
        ->assertJsonPath('errors.birthdate.0', 'Birthdate must be within the last 120 years.');

    $this->postJson('/api/register', $payload('valid.birthdate@example.test', ParticipantBirthdate::oldestAllowedDate()))
        ->assertCreated()
        ->assertJsonPath('user.birthdate', ParticipantBirthdate::oldestAllowedDate());
});

test('admin creation requires birthdate for participants but not administrators', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Participant Without Birthdate',
            'email' => 'participant.without.birthdate@example.test',
            'password' => 'SecureParticipant!2026',
            'password_confirmation' => 'SecureParticipant!2026',
            'role' => User::ROLE_RUNNER,
        ])
        ->assertSessionHasErrors('birthdate');

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('min="'.ParticipantBirthdate::oldestAllowedDate().'"', false)
        ->assertSee('max="'.ParticipantBirthdate::latestAllowedDate().'"', false)
        ->assertSee('birthdateInput.required = isRunner', false);
});

test('legacy participants must complete a valid birthdate before event registration', function () {
    $token = 'missing-birthdate-token';
    $participant = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'birthdate' => null,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
    $event = Event::create([
        'title' => 'Birthdate Validation Run',
        'slug' => 'birthdate-validation-run',
        'description' => 'An event used to verify participant birthdate eligibility.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'end_time' => '10:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Racetech',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);
    $category = Category::create([
        'event_id' => $event->id,
        'name' => '5K Open',
        'distance_km' => 5,
        'status' => 'open',
        'scheduled_start_time' => '06:00',
        'scheduled_end_time' => '08:00',
    ]);
    $reason = 'Add a valid birthdate to your profile before registering for an event.';

    $this->withToken($token)
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonPath('data.0.can_register', false)
        ->assertJsonPath('data.0.registration_block_reason', $reason)
        ->assertJsonPath('data.0.category_registration_states.0.can_register', false)
        ->assertJsonPath('data.0.category_registration_states.0.conflict_reason', $reason);

    $registrationPayload = [
        'shirt_size' => 'M',
        'first_aid_kit_confirmed' => true,
        'waiver_accepted' => true,
        'waiver_name' => $participant->name,
    ];

    $this->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", $registrationPayload)
        ->assertUnprocessable()
        ->assertJsonPath('message', $reason)
        ->assertJsonValidationErrors('birthdate');

    $this->withToken($token)
        ->patchJson('/api/me', [
            'name' => $participant->name,
            'birthdate' => today()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birthdate');

    $this->withToken($token)
        ->patchJson('/api/me', [
            'name' => $participant->name,
            'birthdate' => '1995-05-15',
        ])
        ->assertOk()
        ->assertJsonPath('user.birthdate', '1995-05-15');

    $this->withToken($token)
        ->postJson("/api/events/{$event->id}/register/{$category->id}", $registrationPayload)
        ->assertCreated();
});
