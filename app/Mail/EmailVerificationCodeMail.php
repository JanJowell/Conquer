<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your Racetech email address');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transactional',
            text: 'emails.transactional-text',
            with: [
                'preheader' => 'Use this code to verify your Racetech email address.',
                'heading' => 'Verify your email address',
                'greeting' => $this->recipientName ? 'Hello '.$this->recipientName.',' : 'Hello,',
                'intro' => [
                    'Enter the verification code below in the Racetech mobile app to finish setting up your participant account.',
                ],
                'code' => $this->code,
                'details' => ['Code expires' => '15 minutes'],
                'securityNotice' => 'Never share this code with anyone. Racetech staff will not ask you for it.',
                'closing' => 'If you did not create a Racetech account, you can safely ignore this email.',
            ],
        );
    }
}
