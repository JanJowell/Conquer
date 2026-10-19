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

test('participant can save measurements and receives a calculated adult bmi with a system managed date', function () {
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
        ->assertJsonPath('user.bmi_measured_at', now()->toDateString())
        ->assertJsonPath('user.bmi_notice', ParticipantHealthProfile::BMI_NOTICE);

    $user->refresh();

    expect($user->height_cm)->toBe('170.00')
        ->and($user->weight_kg)->toBe('65.00')
        ->and($user->bmi)->toBe(22.5)
        ->and($user->bmi_measured_at?->toDateString())->toBe(now()->toDateString())
        ->and($user->health_profile_updated_at)->not->toBeNull();
});

test('date-only profile updates cannot rewrite the automatic measurement date', function () {
    $token = 'participant-health-date-only-token';
    $user = participantHealthUser($token, [
        'height_cm' => 170,
        'weight_kg' => 65,
        'bmi_measured_at' => '2026-10-01',
        'health_profile_updated_at' => '2026-10-01 08:00:00',
    ]);

    $this->withToken($token)->patchJson('/api/me', [
        'name' => $user->name,
        'bmi_measured_at' => '2026-10-10',
    ])->assertOk()->assertJsonPath('user.bmi_measured_at', '2026-10-01');

    expect($user->fresh()->health_profile_updated_at?->toDateTimeString())
        ->toBe('2026-10-01 08:00:00');
});

test('offline health updates are idempotent', function () {
    $token = 'participant-health-idempotent-token';
    $user = participantHealthUser($token);
    $updateId = '9d31edcb-9418-4a0f-84c9-264ba5bbfa1e';

    $payload = [
        'client_update_id' => $updateId,
        'client_updated_at' => '2026-10-17T08:00:00Z',
        'base_version' => null,
        'height_cm' => 170,
        'weight_kg' => 65,
        'bmi_measured_at' => '2026-10-17',
    ];

    $first = $this->withToken($token)->putJson('/api/me/health-profile', $payload);
    $first->assertOk()
        ->assertJsonPath('sync_status', 'applied')
        ->assertJsonPath('user.bmi_measured_at', '2026-10-17');

    $version = $first->json('user.health_profile_updated_at');

    $this->withToken($token)->putJson('/api/me/health-profile', [
        ...$payload,
        'weight_kg' => 90,
    ])
        ->assertOk()
        ->assertJsonPath('sync_status', 'duplicate')
        ->assertJsonPath('user.weight_kg', 65)
        ->assertJsonPath('user.health_profile_updated_at', $version);

    expect(DB::table('participant_health_updates')->count())->toBe(1);
});

test('stale offline health updates return the latest server profile without overwriting it', function () {
    $token = 'participant-health-conflict-token';
    $user = participantHealthUser($token, [
        'height_cm' => 170,
        'weight_kg' => 65,
        'bmi_measured_at' => '2026-10-01',
        'health_profile_updated_at' => '2026-10-17 08:00:00',
    ]);

    $this->withToken($token)->putJson('/api/me/health-profile', [
        'client_update_id' => '63c7490a-7d9a-40b6-bfad-93efb08e83a8',
        'client_updated_at' => '2026-10-17T09:00:00Z',
        'base_version' => '2026-10-17T07:00:00Z',
        'height_cm' => 180,
        'weight_kg' => 90,
        'bmi_measured_at' => '2026-10-17',
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'health_profile_conflict')
        ->assertJsonPath('user.height_cm', 170)
        ->assertJsonPath('user.weight_kg', 65);

    expect($user->fresh()->weight_kg)->toBe('65.00')
        ->and(DB::table('participant_health_updates')->count())->toBe(0);
});

test('sync derives its date from the capture timestamp and ignores the legacy editable date', function (string $captureTime, string $editableDate) {
    $token = 'participant-health-capture-date-token';
    participantHealthUser($token);
    $captureDate = now()->subDays(2)->toDateString();

    $this->withToken($token)->putJson('/api/me/health-profile', [
        'client_update_id' => '720c76c6-0b8d-4f45-9b7b-220494d71ccc',
        'client_updated_at' => $captureDate.'T'.$captureTime,
        'base_version' => null,
        'height_cm' => 170,
        'weight_kg' => 66,
        'bmi_measured_at' => $editableDate,
    ])->assertOk()->assertJsonPath('user.bmi_measured_at', $captureDate);
})->with([
    'positive offset crossing UTC midnight' => ['00:30:00+08:00', '2000-01-01'],
    'negative offset crossing UTC midnight' => ['23:30:00-05:00', '2999-01-01'],
    'legacy UTC timestamp' => ['12:00:00Z', '2000-01-01'],
]);

test('sync preserves an unchanged measurement date and clears it with both measurements', function (bool $clear) {
    $token = 'participant-health-preserve-date-token';
    $user = participantHealthUser($token, [
        'height_cm' => 170,
        'weight_kg' => 65,
        'bmi_measured_at' => '2026-10-01',
        'health_profile_updated_at' => '2026-10-01 08:00:00',
    ]);

    $response = $this->withToken($token)->putJson('/api/me/health-profile', [
        'client_update_id' => 'e7fdb0cc-cd4f-4468-a5ac-f136baea72ac',
        'client_updated_at' => now()->subDay()->toIso8601String(),
        'base_version' => $user->health_profile_updated_at->toIso8601String(),
        'height_cm' => $clear ? null : 170,
        'weight_kg' => $clear ? null : 65,
        'bmi_measured_at' => '2000-01-01',
    ])->assertOk()->assertJsonPath('user.bmi_measured_at', $clear ? null : '2026-10-01');

    if ($clear) {
        $response->assertJsonPath('user.bmi', null);
    }
})->with(['unchanged' => false, 'cleared' => true]);

test('offline health update retries require both measurements and valid metadata', function () {
    $token = 'participant-health-offline-validation-token';
    participantHealthUser($token);

    $this->withToken($token)->putJson('/api/me/health-profile', [
        'client_update_id' => 'not-a-uuid',
        'height_cm' => 170,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'client_update_id',
            'client_updated_at',
            'weight_kg',
        ]);
});

test('health sync rejects a future capture timestamp instead of storing a future measurement date', function () {
    $token = 'participant-health-future-capture-token';
    $user = participantHealthUser($token);

    $this->withToken($token)->putJson('/api/me/health-profile', [
        'client_update_id' => '4e9a3d42-d78f-4588-9d4f-6296f1ff56a0',
        'client_updated_at' => now()->addDay()->toIso8601String(),
        'base_version' => null,
        'height_cm' => 170,
        'weight_kg' => 65,
    ])->assertUnprocessable()->assertJsonValidationErrors('client_updated_at');

    expect($user->fresh()->bmi_measured_at)->toBeNull();
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
