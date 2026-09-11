<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminInvitationService
{
    public function send(User $user, User $inviter): void
    {
        if (! $user->isAdmin() || $user->email_verified_at !== null) {
            throw new InvalidArgumentException('Only unverified administrator accounts can receive an invitation.');
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'admin_invitation_token' => hash('sha256', $plainToken),
            'admin_invitation_sent_at' => now(),
            'admin_invitation_expires_at' => now()->addHours(24),
            'admin_invited_by' => $inviter->getKey(),
        ])->save();

        $user->notify(new AdminInvitationNotification($plainToken));
    }
}
