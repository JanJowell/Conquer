<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('role retirement keeps accounts and personal data while revoking only affected authentication state', function () {
    $retired = collect(['executive', 'content_moderator'])->map(fn ($role) => User::factory()->create([
        'role' => $role, 'api_token' => Str::random(80), 'api_token_expires_at' => now()->addDay(),
        'remember_token' => Str::random(10), 'two_factor_required' => true,
        'two_factor_secret' => encrypt('test-only-existing-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['test-only-recovery-code'])),
        'two_factor_confirmed_at' => now(),
        'admin_invitation_token' => Str::random(64), 'admin_invitation_sent_at' => now(),
        'admin_invitation_expires_at' => now()->addDay(),
    ]));
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $participant = User::factory()->create(['role' => User::ROLE_RUNNER, 'api_token' => Str::random(80)]);
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $otherBefore = collect([$admin, $participant, $manager])->mapWithKeys(fn ($user) => [$user->id => $user->refresh()->getAttributes()]);
    $before = $retired->mapWithKeys(fn ($user) => [$user->id => $user->refresh()->getAttributes()]);
    foreach ($retired->concat([$admin, $participant]) as $user) {
        DB::table('sessions')->insert([
            'id' => 'test-session-'.$user->id, 'user_id' => $user->id,
            'payload' => base64_encode('test-only'), 'last_activity' => time(),
        ]);
    }
    $migration = require database_path('migrations/2026_10_30_000001_retire_executive_and_content_moderator_roles.php');
    $migration->up();
    $changedColumns = ['role', 'api_token', 'api_token_expires_at', 'remember_token',
        'admin_invitation_token', 'admin_invitation_sent_at', 'admin_invitation_expires_at',
        'admin_invited_by', 'two_factor_required'];
    foreach ($retired as $user) {
        $user->refresh();
        expect($user->role)->toBe(User::ROLE_RUNNER)->and($user->isAdmin())->toBeFalse()
            ->and($user->two_factor_required)->toBeFalse();
        foreach (array_diff($changedColumns, ['role', 'two_factor_required']) as $column) {
            expect($user->getRawOriginal($column))->toBeNull();
        }
        expect(array_diff_key($user->getAttributes(), array_flip($changedColumns)))
            ->toBe(array_diff_key($before[$user->id], array_flip($changedColumns)));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }
    foreach ([$admin, $participant, $manager] as $user) {
        expect($user->fresh()->getAttributes())->toBe($otherBefore[$user->id]);
    }
    $this->assertDatabaseHas('sessions', ['id' => 'test-session-'.$admin->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'test-session-'.$participant->id]);
    expect(User::count())->toBe(5);
    // Re-running or rolling back code must not recreate privileges or tokens.
    $migration->up();
    $migration->down();
    expect(User::whereIn('id', $retired->pluck('id'))->where('role', User::ROLE_RUNNER)->count())->toBe(2);
});

test('role retirement is a no-op when no affected users exist', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $original = $admin->refresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_30_000001_retire_executive_and_content_moderator_roles.php');
    $migration->up();
    expect($admin->fresh()->getAttributes())->toBe($original)->and(User::count())->toBe(1);
});
