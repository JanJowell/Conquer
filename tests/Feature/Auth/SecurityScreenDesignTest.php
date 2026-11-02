<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

test('password confirmation uses branded layout and preserves password verification', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('password.confirm'))
        ->assertOk()->assertSee('Racetech')->assertSee('Confirm and continue')
        ->assertSee('Back to profile')->assertSee('autocomplete="current-password"', false);
    $this->post(route('password.confirm.store'), ['password' => 'incorrect'])
        ->assertSessionHasErrors('password')->assertSessionMissing('auth.password_confirmed_at');
    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertSessionHas('auth.password_confirmed_at');
});

test('challenge modes show only their own credential field', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->withSession(['login.id' => $user->id])->get(route('two-factor.login'))
        ->assertOk()->assertSee('Two-factor verification')->assertSee('Authentication code')
        ->assertSee('Verify and sign in')->assertSee('Back to login')
        ->assertDontSee('name="recovery_code"', false);
    $this->get(route('two-factor.login', ['recovery' => 1]))
        ->assertOk()->assertSee('Each recovery code can only be used once.')
        ->assertSee('name="recovery_code"', false)->assertDontSee('name="code"', false);
    $this->assertGuest();
});

test('invalid recovery code keeps recovery mode and never authenticates', function () {
    $user = User::factory()->withTwoFactor()->create();
    $url = route('two-factor.login', ['recovery' => 1]);
    $this->withSession(['login.id' => $user->id])->from($url)->followingRedirects()
        ->post(route('two-factor.login.store'), ['recovery_code' => 'not-a-valid-code'])
        ->assertOk()->assertSee('class="security-error"', false)
        ->assertSee('name="recovery_code"', false)->assertDontSee('not-a-valid-code');
    $this->assertGuest();
});

test('cancel challenge removes pending login and requires password again', function () {
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt((new Google2FA)->generateSecretKey())]);
    $secret = decrypt($user->two_factor_secret);
    $this->withSession(['login.id' => $user->id, 'login.remember' => true, 'url.intended' => '/admin/users'])
        ->post(route('two-factor.cancel'))->assertRedirect(route('login'))
        ->assertSessionMissing('login.id')->assertSessionMissing('login.remember')
        ->assertSessionMissing('url.intended');
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    $this->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect(route('two-factor.login'));
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->two_factor_secret)->toBe($user->two_factor_secret);
});

test('cancellation is post only and cannot log out an authenticated user', function () {
    $this->get(route('two-factor.cancel'))->assertStatus(405);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('two-factor.cancel'))->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('two factor challenge retains the existing attempt limit', function () {
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt((new Google2FA)->generateSecretKey())]);
    $this->withSession(['login.id' => $user->id]);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('two-factor.login.store'), ['code' => 'invalid'])->assertSessionHasErrors('code');
    }
    $this->post(route('two-factor.login.store'), ['code' => 'invalid'])->assertStatus(429);
    $this->assertGuest();
});
