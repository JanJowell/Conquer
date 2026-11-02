<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('admins cannot self-delete through profile even when another active admin exists', function (bool $hasOtherAdmin) {
    Storage::fake('public');
    $avatarPath = 'avatars/protected-admin.png';
    Storage::disk('public')->put($avatarPath, 'synthetic-avatar');
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'avatar_path' => $avatarPath,
    ]);
    if ($hasOtherAdmin) {
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }
    $before = $admin->fresh()->getAttributes();

    $this->actingAs($admin)
        ->withSession(['profile_delete_proof' => 'preserve-session'])
        ->delete(route('profile.destroy'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('error', 'Admin accounts cannot be deleted from Profile Settings. Ask another Admin to manage your account through User Management.')
        ->assertSessionHas('profile_delete_proof', 'preserve-session');

    $this->assertAuthenticatedAs($admin);
    expect($admin->fresh()->getAttributes())->toBe($before);
    Storage::disk('public')->assertExists($avatarPath);
    $this->get(route('profile.edit'))->assertOk()
        ->assertSee('Admin accounts cannot be deleted from Profile Settings.');
})->with(['only active Admin' => [false], 'another active Admin' => [true]]);

test('profile deletion cannot bypass the admin block by submitting a different role or target', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $other = User::factory()->create(['role' => User::ROLE_RUNNER]);

    $this->actingAs($admin)->delete(route('profile.destroy'), [
        'role' => User::ROLE_RUNNER,
        'user_id' => $other->id,
        'password' => 'password',
    ])->assertRedirect(route('profile.edit'))->assertSessionHas('error');

    $this->assertAuthenticatedAs($admin);
    $this->assertModelExists($admin);
    $this->assertModelExists($other);
});

test('profile deletion rechecks the stored role if an account was promoted during the request', function () {
    Storage::fake('public');
    $user = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($user);
    // Simulate a promotion after authentication loaded its User instance.
    DB::table('users')->where('id', $user->id)->update(['role' => User::ROLE_SUPER_ADMIN]);

    $this->delete(route('profile.destroy'))
        ->assertRedirect(route('profile.edit'))->assertSessionHas('error');

    $this->assertAuthenticated();
    expect($user->fresh()->role)->toBe(User::ROLE_SUPER_ADMIN);
});

test('admins cannot self-delete through user management even with another active admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', 'You cannot delete your own account.');

    $this->assertAuthenticatedAs($admin);
    $this->assertModelExists($admin);
});

test('an admin can still delete another admin through user management when an active admin remains', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $other))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'User deleted successfully.');

    $this->assertAuthenticatedAs($admin);
    $this->assertModelExists($admin);
    $this->assertModelMissing($other);
});

test('non-admin roles cannot delete an admin through user management', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($user)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    $this->assertModelExists($user);
    $this->assertModelExists($admin);
})->with([User::ROLE_EVENT_MANAGER, User::ROLE_RUNNER]);

test('guests cannot delete a profile', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->delete(route('profile.destroy'))->assertRedirect(route('login'));

    $this->assertModelExists($admin);
});
