<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.invitations.show', [
            'user' => $notifiable->getKey(),
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Activate your RACETECH administrator account')
            ->view([
                'html' => 'emails.transactional',
                'text' => 'emails.transactional-text',
            ], [
                'preheader' => 'Activate your Racetech administrator account and create your private password.',
                'heading' => 'Administrator invitation',
                'greeting' => 'Hello '.$notifiable->name.',',
                'intro' => [
                    'You have been invited to access the Racetech administration system.',
                    'Confirm your email address and create your private password using the secure button below.',
                ],
                'details' => [
                    'Account' => $notifiable->email,
                    'Role' => $notifiable->roleLabel(),
                    'Invitation expires' => '24 hours',
                ],
                'actionLabel' => 'Activate administrator account',
                'actionUrl' => $url,
                'securityNotice' => 'This invitation is single-use. Do not forward this email or share its activation link.',
                'closing' => 'If you were not expecting this invitation, you can safely ignore this email.',
            ]);
    }
}
