<?php

use App\Models\BannedIP;
use App\Models\User;

function securityAdmin(): User
{
    return User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
}

test('super admin can open the security pages', function () {
    $this->actingAs(securityAdmin());

    $this->get(route('admin.security.dashboard'))
        ->assertOk()
        ->assertSee('Security Actions')
        ->assertSee('Login Monitoring')
        ->assertSee('Data Access Logs');

    $this->get(route('admin.security.activity-logs'))->assertOk();
    $this->get(route('admin.security.banned-ips'))->assertOk();
    $this->get(route('admin.security.login-monitoring'))->assertOk();
    $this->get(route('admin.security.data-access-logs'))->assertOk();
});

test('banning the same ip updates the existing ban', function () {
    $this->actingAs(securityAdmin());

    $this->post(route('admin.security.ban-ip'), [
        'ip_address' => '203.0.113.10',
        'reason' => 'Repeated failed login attempts',
        'permanent' => '1',
    ])->assertRedirect(route('admin.security.banned-ips'));

    $this->post(route('admin.security.ban-ip'), [
        'ip_address' => '203.0.113.10',
        'reason' => 'Abusive traffic',
        'permanent' => '0',
        'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('admin.security.banned-ips'));

    expect(BannedIP::where('ip_address', '203.0.113.10')->count())->toBe(1);

    $this->assertDatabaseHas('banned_i_ps', [
        'ip_address' => '203.0.113.10',
        'reason' => 'Abusive traffic',
        'permanent' => false,
    ]);
});

test('temporary ip bans require an expiration date', function () {
    $this->actingAs(securityAdmin());

    $this->post(route('admin.security.ban-ip'), [
        'ip_address' => '203.0.113.11',
        'reason' => 'Temporary review',
        'permanent' => '0',
    ])->assertSessionHasErrors('expires_at');

    expect(BannedIP::where('ip_address', '203.0.113.11')->exists())->toBeFalse();
});

test('active banned ips cannot access admin security pages', function () {
    BannedIP::create([
        'ip_address' => '127.0.0.1',
        'reason' => 'Blocked test address',
        'permanent' => true,
    ]);

    $this->actingAs(securityAdmin());

    $this->get(route('admin.security.dashboard'))->assertForbidden();
});

test('admins required to set up two factor are redirected to profile', function () {
    $admin = securityAdmin();
    $admin->update([
        'two_factor_required' => true,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.security.dashboard'))
        ->assertRedirect(route('profile.edit'));
});
