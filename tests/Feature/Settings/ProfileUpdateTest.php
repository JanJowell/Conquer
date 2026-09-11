<?php

use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->hasPendingAdminInvitation())->toBeTrue();
    $this->assertGuest();
    Notification::assertSentTo($user, AdminInvitationNotification::class);
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->delete(route('profile.destroy'))
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('authenticated users can delete their account without a password prompt', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->delete(route('profile.destroy'))
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
});
