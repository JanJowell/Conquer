<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

function addVerificationCodeFor(User $user): void
{
    DB::table('email_verification_codes')->insert([
        'email' => $user->email,
        'token' => 'hashed-test-token',
        'created_at' => now()->subDays(8),
    ]);
}

function oldUnverifiedRunner(array $attributes = []): User
{
    return User::factory()->unverified()->create([
        'role' => User::ROLE_RUNNER,
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
        ...$attributes,
    ]);
}

test('dry run reports eligible accounts without deleting them', function () {
    $runner = oldUnverifiedRunner();
    addVerificationCodeFor($runner);

    $this->artisan('users:prune-unverified --dry-run')
        ->expectsOutput('Dry run: 1 unverified runner account(s) are eligible for deletion.')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $runner->id]);
    $this->assertDatabaseHas('email_verification_codes', ['email' => $runner->email]);
});

test('cleanup deletes only abandoned unverified runners and their temporary codes', function () {
    $runner = oldUnverifiedRunner();
    addVerificationCodeFor($runner);

    DB::table('password_reset_tokens')->insert([
        'email' => $runner->email,
        'token' => 'hashed-reset-token',
        'created_at' => now(),
    ]);

    $this->artisan('users:prune-unverified')
        ->expectsOutput('Deleted 1 abandoned unverified runner account(s).')
        ->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $runner->id]);
    $this->assertDatabaseMissing('email_verification_codes', ['email' => $runner->email]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $runner->email]);
});

test('cleanup preserves recent verified privileged active and manually created accounts', function () {
    $recent = oldUnverifiedRunner([
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);
    addVerificationCodeFor($recent);

    $verified = oldUnverifiedRunner(['email_verified_at' => now()]);
    addVerificationCodeFor($verified);

    $admin = oldUnverifiedRunner(['role' => User::ROLE_SUPER_ADMIN]);
    addVerificationCodeFor($admin);

    $withoutVerificationCode = oldUnverifiedRunner();

    $loggedIn = oldUnverifiedRunner(['last_login_at' => now()->subDay()]);
    addVerificationCodeFor($loggedIn);

    $tokenHolder = oldUnverifiedRunner([
        'api_token' => hash('sha256', 'test-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    addVerificationCodeFor($tokenHolder);

    $withSession = oldUnverifiedRunner();
    addVerificationCodeFor($withSession);
    DB::table('sessions')->insert([
        'id' => 'protected-unverified-runner-session',
        'user_id' => $withSession->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test agent',
        'payload' => 'test',
        'last_activity' => now()->timestamp,
    ]);

    $this->artisan('users:prune-unverified')
        ->expectsOutput('Deleted 0 abandoned unverified runner account(s).')
        ->assertSuccessful();

    foreach ([$recent, $verified, $admin, $withoutVerificationCode, $loggedIn, $tokenHolder, $withSession] as $user) {
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
});

test('cleanup retention period can be overridden safely', function () {
    $runner = oldUnverifiedRunner([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    addVerificationCodeFor($runner);

    $this->artisan('users:prune-unverified --days=2')->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $runner->id]);

    $this->artisan('users:prune-unverified --days=0')
        ->expectsOutput('The --days option must be a whole number greater than zero.')
        ->assertExitCode(2);
});

test('unverified runner cleanup is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduledEvent) => str_contains(
            $scheduledEvent->command,
            'users:prune-unverified --days=7'
        ));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 3 * * *');
});
