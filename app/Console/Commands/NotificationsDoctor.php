<?php

namespace App\Console\Commands;

use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Console\Command;

class NotificationsDoctor extends Command
{
    protected $signature = 'notifications:doctor';

    protected $description = 'Check push notification configuration and target readiness.';

    public function handle(FirebaseCloudMessaging $messaging): int
    {
        $credentialsPath = config('services.firebase.credentials');
        $resolvedPath = $credentialsPath
            ? (preg_match('/^[A-Za-z]:\\\\/', $credentialsPath) || str_starts_with($credentialsPath, DIRECTORY_SEPARATOR)
                ? $credentialsPath
                : base_path($credentialsPath))
            : null;

        $this->components->info('Push notification readiness');
        $this->line('Firebase project: '.(config('services.firebase.project_id') ?: 'missing'));
        $this->line('Credential file: '.($credentialsPath ?: 'not set'));
        $this->line('Credential file exists: '.($resolvedPath && is_file($resolvedPath) ? 'yes' : 'no'));
        $this->line('Credential JSON env: '.(config('services.firebase.credentials_json') ? 'set' : 'missing'));
        $this->line('Credential base64 env: '.(config('services.firebase.credentials_base64') ? 'set' : 'missing'));
        $this->line('Client email env: '.(config('services.firebase.client_email') ? 'set' : 'missing'));
        $this->line('Private key env: '.(config('services.firebase.private_key') ? 'set' : 'missing'));
        $this->line('FCM configured: '.($messaging->isConfigured() ? 'yes' : 'no'));
        $this->newLine();

        $this->components->info('Audience readiness');
        $this->line('Saved device tokens: '.number_format(UserDeviceToken::count()));
        $this->line('All users: '.number_format(User::count()));
        $this->line('Runners: '.number_format(User::whereIn('role', [User::ROLE_RUNNER, 'user'])->count()));
        $this->line('Participants: '.number_format(User::whereHas('registrations')->count()));
        $this->line('Admin users: '.number_format(User::whereIn('role', User::storedAdminRoles())->count()));
        $this->newLine();

        $this->components->info('Notification queue');
        $this->line('Total notifications: '.number_format(PushNotification::count()));
        $this->line('Active unsent scheduled: '.number_format(
            PushNotification::where('is_active', true)
                ->whereNull('sent_at')
                ->whereNotNull('scheduled_at')
                ->count()
        ));

        return $messaging->isConfigured() ? self::SUCCESS : self::FAILURE;
    }
}
