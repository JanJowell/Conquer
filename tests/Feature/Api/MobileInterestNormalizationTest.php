<?php

use App\Models\User;

function interestNormalizationMobileUser(string $token = 'interest-normalization-token'): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
}

test('mobile registration normalizes known interests while keeping unknown interests', function () {
    $this
        ->postJson('/api/register', [
            'name' => 'Interest Runner',
            'username' => 'interest_runner',
            'email' => 'interest.runner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'interests' => [' marathon ', 'MARATHON', 'trail run', 'Custom Hobby'],
        ])
        ->assertCreated()
        ->assertJsonPath('user.interests.0', 'Marathon')
        ->assertJsonPath('user.interests.1', 'Trail Run')
        ->assertJsonPath('user.interests.2', 'Custom Hobby');

    expect(User::where('email', 'interest.runner@example.com')->first()->interests)
        ->toBe(['Marathon', 'Trail Run', 'Custom Hobby']);
});

test('mobile interest update normalizes case and removes duplicate interests', function () {
    $token = 'interest-update-token';
    interestNormalizationMobileUser($token);

    $this
        ->withToken($token)
        ->patchJson('/api/me/interests', [
            'interests' => [' triathlon ', 'TRIATHLON', 'Food', 'food'],
        ])
        ->assertOk()
        ->assertJsonPath('user.interests.0', 'Triathlon')
        ->assertJsonPath('user.interests.1', 'Food');
});

test('mobile profile update normalizes interests when provided', function () {
    $token = 'profile-interest-token';
    $user = interestNormalizationMobileUser($token);

    $this
        ->withToken($token)
        ->patchJson('/api/me', [
            'name' => $user->name,
            'interests' => [' cycling ', 'CYCLING', 'Wellness'],
        ])
        ->assertOk()
        ->assertJsonPath('user.interests.0', 'Cycling')
        ->assertJsonPath('user.interests.1', 'Wellness');
});
