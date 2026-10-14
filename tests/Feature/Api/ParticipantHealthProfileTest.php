<?php

use App\Models\User;
use App\Support\ParticipantHealthProfile;

function participantHealthUser(string $token, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
        'birthdate' => '1995-05-15',
    ], $attributes));
}

test('participant can save measurements and receives a calculated adult bmi', function () {
    $token = 'participant-health-token';
    $user = participantHealthUser($token);

    $response = $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'name' => $user->name,
            'height_cm' => 170,
            'weight_kg' => 65,
            'bmi_measured_at' => '2026-10-01',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.height_cm', 170)
        ->assertJsonPath('user.weight_kg', 65)
        ->assertJsonPath('user.bmi', 22.5)
        ->assertJsonPath('user.bmi_category', 'Healthy weight')
        ->assertJsonPath('user.bmi_interpretation', 'adult')
        ->assertJsonPath('user.bmi_measured_at', '2026-10-01')
        ->assertJsonPath('user.bmi_notice', ParticipantHealthProfile::BMI_NOTICE);

    $user->refresh();

    expect($user->height_cm)->toBe('170.00')
        ->and($user->weight_kg)->toBe('65.00')
        ->and($user->bmi)->toBe(22.5);
});

test('participant measurements require height and weight together', function () {
    $token = 'participant-health-validation-token';
    $user = participantHealthUser($token);

    $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'name' => $user->name,
            'height_cm' => 170,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('weight_kg');
});

test('participant can clear health measurements without leaving a stale bmi', function () {
    $token = 'participant-health-clear-token';
    $user = participantHealthUser($token, [
        'height_cm' => 170,
        'weight_kg' => 65,
        'bmi_measured_at' => '2026-10-01',
    ]);

    $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'name' => $user->name,
            'height_cm' => null,
            'weight_kg' => null,
        ])
        ->assertOk()
        ->assertJsonPath('user.height_cm', null)
        ->assertJsonPath('user.weight_kg', null)
        ->assertJsonPath('user.bmi', null)
        ->assertJsonPath('user.bmi_category', null)
        ->assertJsonPath('user.bmi_measured_at', null);
});

test('adult bmi categories are not applied to participants under twenty', function () {
    $token = 'minor-health-token';
    $user = participantHealthUser($token, [
        'birthdate' => '2010-05-15',
        'height_cm' => 160,
        'weight_kg' => 50,
        'bmi_measured_at' => '2026-10-01',
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.bmi', 19.5)
        ->assertJsonPath('user.bmi_category', null)
        ->assertJsonPath('user.bmi_interpretation', 'age_specific_required');
});

test('future measurement dates and implausible measurement ranges are rejected', function () {
    $token = 'invalid-health-token';
    $user = participantHealthUser($token);

    $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'name' => $user->name,
            'height_cm' => 20,
            'weight_kg' => 700,
            'bmi_measured_at' => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['height_cm', 'weight_kg', 'bmi_measured_at']);
});
