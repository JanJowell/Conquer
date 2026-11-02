<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

test('admin can enable confirm and disable two factor authentication', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $this->post(route('two-factor.enable'))->assertRedirect(route('password.confirm'));
    expect($admin->fresh()->two_factor_secret)->toBeNull();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('profile.edit'))
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('profile.edit'));

    $admin->refresh();
    expect($admin->two_factor_secret)->not->toBeNull();
    expect($admin->two_factor_confirmed_at)->toBeNull();
    $this->get(route('profile.edit'))->assertOk()->assertSee('Confirmation needed')->assertSee('<svg', false);

    $this->post(route('two-factor.confirm'), ['code' => 'invalid'])
        ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');
    expect($admin->fresh()->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA)->getCurrentOtp(decrypt($admin->two_factor_secret));
    $this->post(route('two-factor.confirm'), ['code' => $code])->assertSessionHasNoErrors();
    expect($admin->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->get(route('profile.edit'))->assertOk()->assertSee('Recovery Codes');

    $codes = $admin->fresh()->recoveryCodes();
    $this->post(route('two-factor.regenerate-recovery-codes'))->assertSessionHasNoErrors();
    expect($admin->fresh()->recoveryCodes())->not->toBe($codes);

    $this->delete(route('two-factor.disable'))->assertSessionHasNoErrors();
    expect($admin->fresh()->two_factor_secret)->toBeNull();
    expect($admin->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('admin sign in requires a valid authenticator code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $admin = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt($secret)]);

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->get(route('two-factor.login'))->assertOk()->assertSee('Authentication code');

    $this->post(route('two-factor.login.store'), ['code' => 'invalid'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->assertAuthenticatedAs($admin);
});

test('admin recovery code permits sign in and is consumed', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->assertAuthenticatedAs($admin);
    expect($admin->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});
