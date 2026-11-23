<?php

namespace App\Console\Commands;

use App\Services\AutomaticEventArchiver;
use Illuminate\Console\Command;

class ArchiveCompletedEvents extends Command
{
    protected $signature = 'events:archive-completed {--dry-run : Count eligible events without changing records}';

    protected $description = 'Archive eligible completed or cancelled events after their 30-day retention window.';

    public function handle(AutomaticEventArchiver $archiver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && config('event_archiving.enabled') !== true) {
            $this->info('Automatic event archiving is disabled.');

            return self::SUCCESS;
        }
        if (! $archiver->available()) {
            $this->error('Automatic event archiving requires its database migration. No events were changed.');

            return self::FAILURE;
        }
        if ($archiver->activationTime() === null) {
            $this->error('Automatic event archiving requires EVENT_AUTO_ARCHIVE_ACTIVATED_AT with a fixed ISO-8601 timestamp and timezone offset. No events were changed.');

            return self::FAILURE;
        }
        $count = $archiver->run($dryRun);
        $this->info($dryRun
            ? "Dry run: {$count} completed/cancelled event(s) are eligible for automatic archiving."
            : "Automatically archived {$count} completed/cancelled event(s).");

        return self::SUCCESS;
    }
}
