<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\NotificationController;
use App\Models\PushNotification;
use Illuminate\Console\Command;

class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';

    protected $description = 'Send due scheduled push notifications.';

    public function handle(NotificationController $controller): int
    {
        $notifications = PushNotification::query()
            ->where('is_active', true)
            ->whereNull('sent_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($notifications as $notification) {
            $result = $controller->deliver($notification);
            $this->line("Notification {$notification->id}: {$result['message']}");
        }

        $this->info("Processed {$notifications->count()} scheduled notification(s).");

        return self::SUCCESS;
    }
}
