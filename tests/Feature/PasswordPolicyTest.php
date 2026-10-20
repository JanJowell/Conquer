<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

dataset('invalid new passwords', [
    'shorter than twelve characters' => ['Short1!abcd', 'Short1!abcd'],
    'missing uppercase' => ['lowercase123!', 'lowercase123!'],
    'missing lowercase' => ['UPPERCASE123!', 'UPPERCASE123!'],
    'missing number' => ['MixedCaseOnly!', 'MixedCaseOnly!'],
    'missing symbol' => ['MixedCase1234', 'MixedCase1234'],
    'confirmation mismatch' => ['SecureParticipant!2026', 'DifferentParticipant!2026'],
]);

test('password entry points reject invalid new passwords without changing accounts', function (
    string $flow,
    string $password,
    string $confirmation
) {
    Mail::fake();

    $user = User::factory()->create([
        'role' => in_array($flow, ['mobile change', 'mobile reset']) ? User::ROLE_RUNNER : User::ROLE_SUPER_ADMIN,
        'password' => 'password',
        'api_token' => hash('sha256', 'password-policy-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    $originalHash = $user->password;
    $input = ['password' => $password, 'password_confirmation' => $confirmation];
    $newParticipant = [
        'name' => 'Password Policy Participant',
        'email' => 'password.policy@example.test',
        'birthdate' => '1995-05-15',
        ...$input,
    ];

    switch ($flow) {
        case 'mobile signup':
            $response = $this->postJson('/api/register', $newParticipant);
            break;
        case 'admin creates participant':
            $response = $this->actingAs($user)->postJson(route('admin.users.store'), [
                ...$newParticipant, 'role' => User::ROLE_RUNNER,
            ]);
            break;
        case 'mobile change':
            $response = $this->withToken('password-policy-token')->patchJson('/api/me/password', [
                ...$input, 'current_password' => 'password',
            ]);
            break;
        case 'mobile reset':
            DB::table('password_reset_tokens')->insert([
                'email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now(),
            ]);
            $response = $this->postJson('/api/reset-password', [
                ...$input, 'email' => $user->email, 'code' => '123456',
            ]);
            $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
            break;
        case 'web change':
            $response = $this->actingAs($user)->patchJson(route('profile.password.update'), [
                ...$input, 'current_password' => 'password',
            ]);
            break;
        case 'web reset':
            $resetToken = Password::broker()->createToken($user);
            $response = $this->postJson(route('password.update'), [
                ...$input, 'email' => $user->email, 'token' => $resetToken,
            ]);
            expect(Password::broker()->tokenExists($user, $resetToken))->toBeTrue();
            break;
        case 'admin activation':
            $user->forceFill([
                'email_verified_at' => null,
                'admin_invitation_token' => hash('sha256', 'invitation-token'),
                'admin_invitation_expires_at' => now()->addDay(),
            ])->save();
            $response = $this->postJson(route('admin.invitations.accept', [$user, 'invitation-token']), $input);
            expect($user->fresh()->email_verified_at)->toBeNull()
                ->and($user->fresh()->admin_invitation_token)->toBe(hash('sha256', 'invitation-token'));
            break;
    }

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($user->fresh()->password)->toBe($originalHash);
    $this->assertDatabaseCount('users', 1);
    Mail::assertNothingSent();
})->with([
    'mobile signup', 'admin creates participant', 'mobile change', 'mobile reset',
    'web change', 'web reset', 'admin activation',
])->with('invalid new passwords');

test('the Fortify account creation action enforces the shared password policy', function (string $password, string $confirmation) {
    try {
        app(CreateNewUser::class)->create([
            'name' => 'Password Policy Participant',
            'email' => 'fortify.policy@example.test',
            'birthdate' => '1995-05-15',
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]);
        $this->fail('Invalid password was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('password');
    }

    $this->assertDatabaseCount('users', 0);
})->with('invalid new passwords');

test('mobile password changes accept strong passwords through legacy field aliases', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'password' => 'password',
        'api_token' => hash('sha256', 'password-policy-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);

    $this->withToken('password-policy-token')->patchJson('/api/me/password', [
        'current_password' => 'password',
        'new_password' => 'NewParticipant!2026',
        'confirm_password' => 'NewParticipant!2026',
    ])->assertOk();

    expect(Hash::check('NewParticipant!2026', $user->fresh()->password))->toBeTrue();
});

test('mobile reset accepts a strong password and consumes its reset code', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'old-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now(),
    ]);

    $this->postJson('/api/reset-password', [
        'email' => $user->email,
        'code' => '123456',
        'password' => 'NewParticipant!2026',
        're_enter_password' => 'NewParticipant!2026',
    ])->assertOk();

    expect(Hash::check('NewParticipant!2026', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->api_token)->toBeNull()
        ->and($user->fresh()->api_token_expires_at)->toBeNull();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('existing weaker passwords remain usable for participant and administrator login', function () {
    $participant = User::factory()->create(['role' => User::ROLE_RUNNER, 'password' => 'password']);
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'password' => 'password']);

    $this->postJson('/api/login', ['email' => $participant->email, 'password' => 'password'])->assertOk();
    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);
});

test('the shared default keeps its twelve character boundary in every environment', function (string $environment) {
    $originalEnvironment = $this->app['env'];
    $verifier = $this->mock(UncompromisedVerifier::class);

    if ($environment === 'production') {
        $verifier->shouldReceive('verify')->once()->with(['value' => 'Abcd!1234567', 'threshold' => 0])->andReturnTrue();
    } else {
        $verifier->shouldNotReceive('verify');
    }

    try {
        $this->app['env'] = $environment;
        expect(Validator::make(['password' => 'Abcd!1234567'], [
            'password' => ['required', 'string', PasswordRule::defaults()],
        ])->passes())->toBeTrue();
        expect(Validator::make(['password' => 'Abcd!123456'], [
            'password' => ['required', 'string', PasswordRule::defaults()],
        ])->fails())->toBeTrue();
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
})->with(['local', 'testing', 'production']);

test('production still rejects a password reported as compromised', function () {
    $originalEnvironment = $this->app['env'];
    $this->mock(UncompromisedVerifier::class)
        ->shouldReceive('verify')->once()->andReturnFalse();

    try {
        $this->app['env'] = 'production';
        $validation = Validator::make(['password' => 'SecureParticipant!2026'], [
            'password' => ['required', 'string', PasswordRule::defaults()],
        ]);

        expect($validation->fails())->toBeTrue()
            ->and($validation->errors()->first('password'))->toContain('data leak');
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
});
