<?php

use App\Models\User;

test('admin user creation provides accessible password visibility controls', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('type="password"', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('data-password-toggle="password"', false)
        ->assertSee('aria-controls="password"', false)
        ->assertSee('aria-label="Show password"', false)
        ->assertSee('data-password-toggle="password_confirmation"', false)
        ->assertSee('aria-controls="password_confirmation"', false)
        ->assertSee('aria-label="Show password confirmation"', false)
        ->assertSee('fa-eye-slash', false);
});
