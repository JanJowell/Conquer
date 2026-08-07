<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class RequestCommunityPostPurge extends Command
{
    protected $signature = 'community-posts:request-purge
        {--days=30 : Permanently delete posts archived for at least this many days}';

    protected $description = 'Securely request archived community post cleanup from the web service.';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($days === false) {
            $this->error('The --days option must be a whole number greater than zero.');

            return self::INVALID;
        }

        $url = URL::temporarySignedRoute(
            'internal.community-posts.purge',
            now()->addMinutes(10),
            ['days' => $days],
        );

        try {
            $response = Http::timeout(300)
                ->retry(2, 1000)
                ->post($url);
        } catch (Throwable $exception) {
            Log::error('Unable to request archived community post cleanup.', [
                'error_type' => $exception::class,
            ]);
            $this->error('The web service could not be reached for archived post cleanup.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            Log::error('Archived community post cleanup request failed.', [
                'status' => $response->status(),
            ]);
            $this->error("The web service rejected archived post cleanup (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $this->info('Archived community post cleanup completed on the web service.');

        return self::SUCCESS;
    }
}
