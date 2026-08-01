<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('mobile registration requires email verification before login', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Mobile Runner',
        'username' => 'mobile_runner',
        'email' => 'mobile.runner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'email_verification_required' => true,
        ]);

    $user = User::where('email', 'mobile.runner@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    $this->assertDatabaseHas('email_verification_codes', [
        'email' => 'mobile.runner@example.com',
    ]);

    $this->postJson('/api/login', [
        'email' => 'mobile.runner@example.com',
        'password' => 'password123',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Please verify your email before logging in.',
            'email_verification_required' => true,
        ]);
});

test('mobile user can verify email code and then login', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'verify.runner@example.com',
        'role' => User::ROLE_RUNNER,
    ]);

    DB::table('email_verification_codes')->insert([
        'email' => $user->email,
        'token' => Hash::make('123456'),
        'created_at' => now(),
    ]);

    $this->postJson('/api/verify-email', [
        'email' => $user->email,
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Email verified successfully. You can now log in.',
        ]);

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    $this->assertDatabaseMissing('email_verification_codes', [
        'email' => $user->email,
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
        ]);
});

test('mobile email verification rejects invalid or expired codes', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'expired.runner@example.com',
        'role' => User::ROLE_RUNNER,
    ]);

    DB::table('email_verification_codes')->insert([
        'email' => $user->email,
        'token' => Hash::make('123456'),
        'created_at' => now()->subMinutes(16),
    ]);

    $this->postJson('/api/verify-email', [
        'email' => $user->email,
        'code' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'The verification code is invalid or has expired.',
        ]);

    expect($user->fresh()->email_verified_at)->toBeNull();
});
