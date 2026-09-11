<?php

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use App\Services\AdminInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('a super admin creates staff as unverified and sends a password setup invitation', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Invited Manager',
            'email' => 'invited.manager@example.com',
            'role' => User::ROLE_EVENT_MANAGER,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $manager = User::where('email', 'invited.manager@example.com')->firstOrFail();

    expect($manager->email_verified_at)->toBeNull()
        ->and($manager->admin_invitation_token)->not->toBeNull()
        ->and($manager->admin_invitation_expires_at)->not->toBeNull()
        ->and($manager->admin_invited_by)->toBe($superAdmin->id)
        ->and($manager->hasPendingAdminInvitation())->toBeTrue();

    Notification::assertSentTo($manager, AdminInvitationNotification::class);
    expect(AdminActivityLog::where('action', 'Invited administrator '.$manager->email)->exists())->toBeTrue();
});

test('an invited administrator activates the account once and creates a private password', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $manager = User::factory()->unverified()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'password' => 'unusable-original-password',
    ]);

    app(AdminInvitationService::class)->send($manager, $superAdmin);

    $token = null;
    Notification::assertSentTo(
        $manager,
        AdminInvitationNotification::class,
        function (AdminInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        }
    );

    $this->get(route('admin.invitations.show', [$manager, $token]))
        ->assertOk()
        ->assertSee('Administrator invitation')
        ->assertSee($manager->email);

    $this->post(route('admin.invitations.accept', [$manager, $token]), [
        'password' => 'SecureAdmin!2026',
        'password_confirmation' => 'SecureAdmin!2026',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $manager->refresh();

    expect($manager->email_verified_at)->not->toBeNull()
        ->and($manager->admin_invitation_token)->toBeNull()
        ->and($manager->admin_invitation_expires_at)->toBeNull()
        ->and(Hash::check('SecureAdmin!2026', $manager->password))->toBeTrue()
        ->and(AdminActivityLog::where('user_id', $manager->id)
            ->where('action', 'Accepted administrator invitation')->exists())->toBeTrue();

    $this->get(route('admin.invitations.show', [$manager, $token]))
        ->assertRedirect(route('login'));
});

test('expired administrator invitations cannot activate an account', function () {
    $token = 'expired-administrator-invitation-token';
    $manager = User::factory()->unverified()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'admin_invitation_token' => hash('sha256', $token),
        'admin_invitation_sent_at' => now()->subHours(25),
        'admin_invitation_expires_at' => now()->subHour(),
    ]);

    $this->get(route('admin.invitations.show', [$manager, $token]))
        ->assertStatus(410)
        ->assertSee('invitation has expired');

    $this->post(route('admin.invitations.accept', [$manager, $token]), [
        'password' => 'SecureAdmin!2026',
        'password_confirmation' => 'SecureAdmin!2026',
    ])->assertSessionHasErrors('invitation');

    expect($manager->fresh()->email_verified_at)->toBeNull();
});

test('resending rotates the invitation and invalidates its previous link', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $manager = User::factory()->unverified()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $service = app(AdminInvitationService::class);

    $service->send($manager, $superAdmin);
    $oldHash = $manager->fresh()->admin_invitation_token;

    $this->actingAs($superAdmin)
        ->post(route('admin.users.resend-invitation', $manager))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($manager->fresh()->admin_invitation_token)->not->toBe($oldHash);
    Notification::assertSentToTimes($manager, AdminInvitationNotification::class, 2);
});

test('unverified administrators are blocked from web and scanner authentication', function () {
    $manager = User::factory()->unverified()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'email' => 'pending.manager@example.com',
        'password' => 'KnownPassword!2026',
    ]);

    $this->post('/login', [
        'email' => $manager->email,
        'password' => 'KnownPassword!2026',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->postJson('/api/staff/login', [
        'email' => $manager->email,
        'password' => 'KnownPassword!2026',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Activate your administrator account using the invitation sent to your email.');

    $this->actingAs($manager)
        ->get(route('admin.dashboard'))
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('changing an administrator email revokes verification and sends a new invitation', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $manager = User::factory()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'email' => 'old.manager@example.com',
        'api_token' => hash('sha256', 'scanner-token'),
        'api_token_expires_at' => now()->addHour(),
    ]);

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $manager), [
            'name' => $manager->name,
            'email' => 'new.manager@example.com',
            'role' => User::ROLE_EVENT_MANAGER,
        ])
        ->assertRedirect(route('admin.users.index'));

    $manager->refresh();

    expect($manager->email_verified_at)->toBeNull()
        ->and($manager->api_token)->toBeNull()
        ->and($manager->api_token_expires_at)->toBeNull()
        ->and($manager->hasPendingAdminInvitation())->toBeTrue();

    Notification::assertSentTo($manager, AdminInvitationNotification::class);
});

test('existing verified administrators retain normal dashboard access', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('runner creation still requires a password', function () {
    $superAdmin = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Runner Without Password',
            'email' => 'runner.without.password@example.com',
            'role' => User::ROLE_RUNNER,
        ])
        ->assertSessionHasErrors('password');

    $this->assertDatabaseMissing('users', ['email' => 'runner.without.password@example.com']);
});

test('mobile verification codes cannot activate administrator accounts', function () {
    $manager = User::factory()->unverified()->create([
        'role' => User::ROLE_EVENT_MANAGER,
        'email' => 'admin.mobile.code@example.com',
    ]);

    DB::table('email_verification_codes')->insert([
        'email' => $manager->email,
        'token' => Hash::make('123456'),
        'created_at' => now(),
    ]);

    $this->postJson('/api/verify-email', [
        'email' => $manager->email,
        'code' => '123456',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Administrator accounts must be activated through their secure web invitation.');

    $this->postJson('/api/resend-verification-code', [
        'email' => $manager->email,
    ])->assertForbidden();

    expect($manager->fresh()->email_verified_at)->toBeNull();
});
