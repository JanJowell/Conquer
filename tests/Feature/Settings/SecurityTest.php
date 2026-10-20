<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('profile security settings are shown on the profile page', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Change Password')
        ->assertSee('Two-Factor Authentication');
});

test('change password fields provide accessible visibility toggles', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-profile-password-toggle="current_password"', false)
        ->assertSee('aria-controls="current_password"', false)
        ->assertSee('aria-label="Show current password"', false)
        ->assertSee('data-profile-password-toggle="password"', false)
        ->assertSee('aria-controls="password"', false)
        ->assertSee('aria-label="Show new password"', false)
        ->assertSee('data-profile-password-toggle="password_confirmation"', false)
        ->assertSee('aria-controls="password_confirmation"', false)
        ->assertSee('aria-label="Show password confirmation"', false)
        ->assertSee('data-password-visible-icon', false)
        ->assertSee('data-password-hidden-icon', false);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'NewSecurePassword!2026',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewSecurePassword!2026', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'NewSecurePassword!2026',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
