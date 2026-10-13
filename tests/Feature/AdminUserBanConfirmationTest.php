<?php

use App\Models\User;

test('user management renders icon-only ban and unban controls with confirmation dialogs', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);
    $activeUser = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'banned_at' => null,
    ]);
    $bannedUser = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'banned_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('aria-label="Ban user"', false)
        ->assertSee('data-open-user-modal="ban-user-'.$activeUser->id.'"', false)
        ->assertSee('id="ban-user-'.$activeUser->id.'"', false)
        ->assertSee('Ban '.$activeUser->name.'?')
        ->assertSee('aria-label="Unban user"', false)
        ->assertSee('data-open-user-modal="unban-user-'.$bannedUser->id.'"', false)
        ->assertSee('id="unban-user-'.$bannedUser->id.'"', false)
        ->assertSee('Unban '.$bannedUser->name.'?');
});

test('a super administrator can confirm banning and unbanning another user', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);
    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'banned_at' => null,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.ban', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect($user->fresh()->banned_at)->not->toBeNull();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.unban', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect($user->fresh()->banned_at)->toBeNull();
});

test('a super administrator cannot ban their own account', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.ban', $superAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect($superAdmin->fresh()->banned_at)->toBeNull();
});
