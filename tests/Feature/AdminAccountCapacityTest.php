<?php

use App\Models\User;
use App\Support\AdministrativeAccountPolicy;
use Illuminate\Support\Facades\Notification;

test('pending suspended and banned administrators all consume their role capacity', function () {
    Notification::fake();
    config(['admin_accounts.limits.executive' => 3]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    User::factory()->unverified()->create(['role' => User::ROLE_EXECUTIVE]);
    User::factory()->create([
        'role' => User::ROLE_EXECUTIVE,
        'suspended_at' => now(),
    ]);
    User::factory()->create([
        'role' => User::ROLE_EXECUTIVE,
        'banned_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), [
            'name' => 'Fourth Executive',
            'email' => 'fourth.executive@example.com',
            'role' => User::ROLE_EXECUTIVE,
        ])
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors([
            'role' => 'The Executive (CEO/COO) account limit of 3 has been reached. Delete an existing Executive (CEO/COO) account or change its role before adding another.',
        ]);

    $this->assertDatabaseMissing('users', ['email' => 'fourth.executive@example.com']);
});

test('an account cannot be promoted into a role that is already at capacity', function () {
    Notification::fake();
    config(['admin_accounts.limits.content_moderator' => 2]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    User::factory()->count(2)->create(['role' => User::ROLE_CONTENT_MODERATOR]);
    $participant = User::factory()->create(['role' => User::ROLE_RUNNER]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $participant), [
            'name' => $participant->name,
            'email' => $participant->email,
            'role' => User::ROLE_CONTENT_MODERATOR,
        ])
        ->assertSessionHasErrors('role');

    expect($participant->fresh()->normalizedRole())->toBe(User::ROLE_RUNNER);
});

test('an existing account remains editable when its unchanged role is at capacity', function () {
    Notification::fake();
    config(['admin_accounts.limits.executive' => 1]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $executive = User::factory()->create(['role' => User::ROLE_EXECUTIVE]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $executive), [
            'name' => 'Updated Executive',
            'email' => $executive->email,
            'role' => User::ROLE_EXECUTIVE,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect($executive->fresh()->name)->toBe('Updated Executive');
});

test('event manager accounts are unlimited by default', function () {
    Notification::fake();
    config(['admin_accounts.limits.event_manager' => null]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    User::factory()->count(8)->create(['role' => User::ROLE_EVENT_MANAGER]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Another Event Manager',
            'email' => 'another.manager@example.com',
            'role' => User::ROLE_EVENT_MANAGER,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'email' => 'another.manager@example.com',
        'role' => User::ROLE_EVENT_MANAGER,
    ]);
});

test('changing the only active admin email is blocked because it would revoke verification', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email' => 'only.admin@example.com',
        'email_verified_at' => now(),
    ]);
    User::factory()->unverified()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => 'new.admin@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ])
        ->assertSessionHasErrors([
            'email' => 'At least one verified, active Admin account must remain. Verify or reactivate another Admin before changing this account.',
        ]);

    expect($admin->fresh()->email)->toBe('only.admin@example.com')
        ->and($admin->fresh()->email_verified_at)->not->toBeNull();
});

test('last active admin protection ignores unavailable admin accounts', function () {
    $activeAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'suspended_at' => now(),
    ]);
    User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'banned_at' => now(),
    ]);

    $policy = app(AdministrativeAccountPolicy::class);

    expect($policy->activeAdminCount())->toBe(1)
        ->and($policy->canDeactivate($activeAdmin))->toBeFalse();

    User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    expect($policy->activeAdminCount())->toBe(2)
        ->and($policy->canDeactivate($activeAdmin))->toBeTrue();
});

test('the last active admin cannot be demoted deleted suspended or banned', function () {
    $unavailableAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'suspended_at' => now(),
    ]);
    $lastActiveAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($unavailableAdmin)
        ->put(route('admin.users.update', $lastActiveAdmin), [
            'name' => $lastActiveAdmin->name,
            'email' => $lastActiveAdmin->email,
            'role' => User::ROLE_EVENT_MANAGER,
        ])
        ->assertSessionHasErrors('role');

    $this->delete(route('admin.users.destroy', $lastActiveAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'one verified, active Admin'));

    $this->post(route('admin.users.suspend', $lastActiveAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'one verified, active Admin'));

    $this->post(route('admin.users.ban', $lastActiveAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'one verified, active Admin'));

    $lastActiveAdmin->refresh();

    expect($lastActiveAdmin->exists)->toBeTrue()
        ->and($lastActiveAdmin->role)->toBe(User::ROLE_SUPER_ADMIN)
        ->and($lastActiveAdmin->suspended_at)->toBeNull()
        ->and($lastActiveAdmin->banned_at)->toBeNull();
});

test('user management shows role counts remaining slots and full roles', function () {
    config([
        'admin_accounts.limits.super_admin' => 1,
        'admin_accounts.limits.executive' => 3,
        'admin_accounts.limits.content_moderator' => 5,
        'admin_accounts.limits.event_manager' => null,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Administrative Account Capacity')
        ->assertSee('data-capacity-summary="1/1"', false)
        ->assertSee('0 slots left')
        ->assertSee('No limit');

    $this->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('1/1 used')
        ->assertSee('Roles at capacity cannot be selected.')
        ->assertSee('value="super_admin"', false)
        ->assertSee('disabled', false);
});
