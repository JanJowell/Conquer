<?php

use App\Mail\EmailVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;

test('participant verification and reset code emails use the branded secure layout', function () {
    $verificationHtml = (new EmailVerificationCodeMail('123456', 'Jamie Runner'))->render();
    $resetHtml = (new PasswordResetCodeMail('654321', 'Jamie Runner'))->render();

    expect($verificationHtml)
        ->toContain('Racetech')
        ->toContain('Verify your email address')
        ->toContain('Hello Jamie Runner,')
        ->toContain('123456')
        ->toContain('15 minutes')
        ->toContain('Security reminder')
        ->and($resetHtml)
        ->toContain('Reset your password')
        ->toContain('654321')
        ->toContain('keep your current password');
});

test('administrator invitation email uses the shared layout with role and fallback link', function () {
    $manager = User::factory()->create([
        'name' => 'Invited Manager',
        'email' => 'standard.invite@example.com',
        'role' => User::ROLE_EVENT_MANAGER,
    ]);
    $message = (new AdminInvitationNotification('plain-invitation-token'))->toMail($manager);
    $html = $message->render();

    expect($message->view)->toBeArray()
        ->and($message->view['html'])->toBe('emails.transactional')
        ->and($message->view['text'])->toBe('emails.transactional-text')
        ->and($html)->toContain('Administrator invitation')
        ->toContain('Invited Manager')
        ->toContain('Event Manager')
        ->toContain('24 hours')
        ->toContain('Activate administrator account')
        ->toContain('plain-invitation-token');
});

test('administrator password reset email uses the branded secure link layout', function () {
    $admin = User::factory()->create([
        'name' => 'Reset Admin',
        'email' => 'standard.reset@example.com',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
    $message = (new ResetPassword('reset-link-token'))->toMail($admin);
    $html = $message->render();

    expect($message->view)->toBeArray()
        ->and($message->view['html'])->toBe('emails.transactional')
        ->and($message->view['text'])->toBe('emails.transactional-text')
        ->and($html)->toContain('Reset your administrator password')
        ->toContain('Reset Admin')
        ->toContain('standard.reset@example.com')
        ->toContain('Reset administrator password')
        ->toContain('reset-link-token');
});

test('mobile authentication endpoints send the dedicated branded mailables', function () {
    Mail::fake();

    $this->postJson('/api/register', [
        'name' => 'Email Design Runner',
        'username' => 'email_design_runner',
        'email' => 'email.design.runner@example.com',
        'birthdate' => '1995-05-15',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail): bool {
        return $mail->hasTo('email.design.runner@example.com')
            && $mail->recipientName === 'Email Design Runner'
            && preg_match('/^\d{6}$/', $mail->code) === 1;
    });

    $this->postJson('/api/forgot-password', [
        'email' => 'email.design.runner@example.com',
    ])->assertOk();

    Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail): bool {
        return $mail->hasTo('email.design.runner@example.com')
            && $mail->recipientName === 'Email Design Runner'
            && preg_match('/^\d{6}$/', $mail->code) === 1;
    });
});
