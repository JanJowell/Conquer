<?php

use App\Models\User;

afterEach(function () {
    foreach ([
        'ADMIN_BOOTSTRAP_ENABLED',
        'ADMIN_BOOTSTRAP_NAME',
        'ADMIN_BOOTSTRAP_EMAIL',
        'ADMIN_BOOTSTRAP_PASSWORD',
    ] as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
});

function setBootstrapAdminEnvironment(array $values): void
{
    foreach ($values as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}

test('administrator bootstrap is inert unless explicitly enabled', function () {
    $this->artisan('admin:bootstrap')
        ->expectsOutputToContain('Administrator bootstrap is disabled.')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('administrator bootstrap creates a verified super administrator', function () {
    setBootstrapAdminEnvironment([
        'ADMIN_BOOTSTRAP_ENABLED' => 'true',
        'ADMIN_BOOTSTRAP_NAME' => 'Staging Administrator',
        'ADMIN_BOOTSTRAP_EMAIL' => 'admin@example.com',
        'ADMIN_BOOTSTRAP_PASSWORD' => 'Strong!Bootstrap42Password',
    ]);

    $this->artisan('admin:bootstrap')->assertSuccessful();

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->role)->toBe(User::ROLE_SUPER_ADMIN)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(password_verify('Strong!Bootstrap42Password', $admin->password))->toBeTrue();
});

test('administrator bootstrap promotes an existing account without changing its password', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => User::ROLE_RUNNER,
        'password' => 'Existing!Password42',
        'email_verified_at' => null,
    ]);

    $originalPasswordHash = $user->password;

    setBootstrapAdminEnvironment([
        'ADMIN_BOOTSTRAP_ENABLED' => 'true',
        'ADMIN_BOOTSTRAP_NAME' => 'Staging Administrator',
        'ADMIN_BOOTSTRAP_EMAIL' => 'admin@example.com',
        'ADMIN_BOOTSTRAP_PASSWORD' => 'Different!Bootstrap42Password',
    ]);

    $this->artisan('admin:bootstrap')->assertSuccessful();

    $user->refresh();

    expect($user->role)->toBe(User::ROLE_SUPER_ADMIN)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBe($originalPasswordHash);
});
