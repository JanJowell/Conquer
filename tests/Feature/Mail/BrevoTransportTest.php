<?php

use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

test('brevo mailer resolves to the HTTPS API transport', function () {
    config()->set('mail.mailers.brevo', [
        'transport' => 'brevo',
        'key' => 'test-api-key',
        'timeout' => 5,
    ]);

    $manager = app(MailManager::class);
    $manager->purge('brevo');

    expect($manager->mailer('brevo')->getSymfonyTransport())
        ->toBeInstanceOf(BrevoApiTransport::class)
        ->and((string) $manager->mailer('brevo')->getSymfonyTransport())
        ->toBe('brevo+api://api.brevo.com');
});

test('brevo mailer rejects a missing API key', function () {
    config()->set('mail.mailers.brevo', [
        'transport' => 'brevo',
        'key' => null,
        'timeout' => 5,
    ]);

    $manager = app(MailManager::class);
    $manager->purge('brevo');

    expect(fn () => $manager->mailer('brevo'))
        ->toThrow(InvalidArgumentException::class, 'BREVO_API_KEY');
});
