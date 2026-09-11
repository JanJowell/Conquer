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
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been invited to access the RACETECH administration system as '.$notifiable->roleLabel().'.')
            ->line('Confirm your email address and create your private password using the button below.')
            ->action('Activate administrator account', $url)
            ->line('This single-use invitation expires in 24 hours.')
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
