<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Racetech password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transactional',
            text: 'emails.transactional-text',
            with: [
                'preheader' => 'Use this code to reset your Racetech password.',
                'heading' => 'Reset your password',
                'greeting' => $this->recipientName ? 'Hello '.$this->recipientName.',' : 'Hello,',
                'intro' => [
                    'We received a request to reset the password for your Racetech participant account.',
                    'Enter the code below in the mobile app to continue.',
                ],
                'code' => $this->code,
                'details' => ['Code expires' => '15 minutes'],
                'securityNotice' => 'Never share this code with anyone. Racetech staff will not ask you for it.',
                'closing' => 'If you did not request a password reset, you can safely ignore this email and keep your current password.',
            ],
        );
    }
}
