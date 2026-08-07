<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneUnverifiedUsers extends Command
{
    /**
     * User references that indicate an account has meaningful activity or ownership.
     *
     * @var list<array{table: string, column: string}>
     */
    private const ACTIVITY_REFERENCES = [
        ['table' => 'registrations', 'column' => 'user_id'],
        ['table' => 'race_results', 'column' => 'user_id'],
        ['table' => 'issued_e_badges', 'column' => 'user_id'],
        ['table' => 'community_posts', 'column' => 'user_id'],
        ['table' => 'community_post_comments', 'column' => 'user_id'],
        ['table' => 'community_post_likes', 'column' => 'user_id'],
        ['table' => 'community_post_hides', 'column' => 'user_id'],
        ['table' => 'community_post_reports', 'column' => 'user_id'],
        ['table' => 'notification_reads', 'column' => 'user_id'],
        ['table' => 'user_device_tokens', 'column' => 'user_id'],
        ['table' => 'payments', 'column' => 'user_id'],
        ['table' => 'sessions', 'column' => 'user_id'],
        ['table' => 'admin_activity_logs', 'column' => 'user_id'],
        ['table' => 'events', 'column' => 'manager_id'],
    ];

    protected $signature = 'users:prune-unverified
        {--days=7 : Delete only accounts at least this many days old}
        {--dry-run : Report the number of eligible accounts without deleting them}';

    protected $description = 'Delete abandoned, unverified runner accounts that have no activity.';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($days === false) {
            $this->error('The --days option must be a whole number greater than zero.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days);
        $eligibleCount = $this->eligibleUsersQuery($cutoff)->count();
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info("Dry run: {$eligibleCount} unverified runner account(s) are eligible for deletion.");
            $this->writeLog($days, $cutoff, $eligibleCount, 0, true);

            return self::SUCCESS;
        }

        $deletedCount = 0;

        $this->eligibleUsersQuery($cutoff)
            ->select('users.id')
            ->chunkById(100, function ($candidates) use ($cutoff, &$deletedCount) {
                foreach ($candidates as $candidate) {
                    $deleted = DB::transaction(function () use ($candidate, $cutoff): bool {
                        $user = $this->eligibleUsersQuery($cutoff)
                            ->whereKey($candidate->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $user) {
                            return false;
                        }

                        DB::table('email_verification_codes')
                            ->where('email', $user->email)
                            ->delete();

                        DB::table('password_reset_tokens')
                            ->where('email', $user->email)
                            ->delete();

                        return (bool) $user->delete();
                    }, 3);

                    if ($deleted) {
                        $deletedCount++;
                    }
                }
            });

        $skippedCount = max(0, $eligibleCount - $deletedCount);

        $this->info("Deleted {$deletedCount} abandoned unverified runner account(s).");

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} account(s) because their eligibility changed during cleanup.");
        }

        $this->writeLog($days, $cutoff, $eligibleCount, $deletedCount, false, $skippedCount);

        return self::SUCCESS;
    }

    private function eligibleUsersQuery(CarbonInterface $cutoff): Builder
    {
        $query = User::query()
            ->whereIn('role', [User::ROLE_RUNNER, 'user'])
            ->whereNull('email_verified_at')
            ->whereNull('api_token')
            ->whereNull('last_login_at')
            ->where('created_at', '<=', $cutoff)
            ->whereExists(function ($verificationQuery) {
                $verificationQuery
                    ->selectRaw('1')
                    ->from('email_verification_codes')
                    ->whereColumn('email_verification_codes.email', 'users.email');
            });

        foreach (self::ACTIVITY_REFERENCES as $reference) {
            $query->whereNotExists(function ($activityQuery) use ($reference) {
                $activityQuery
                    ->selectRaw('1')
                    ->from($reference['table'])
                    ->whereColumn(
                        $reference['table'].'.'.$reference['column'],
                        'users.id'
                    );
            });
        }

        return $query;
    }

    private function writeLog(
        int $days,
        CarbonInterface $cutoff,
        int $eligibleCount,
        int $deletedCount,
        bool $dryRun,
        int $skippedCount = 0,
    ): void {
        Log::info('Unverified runner cleanup completed.', [
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'eligible_count' => $eligibleCount,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'dry_run' => $dryRun,
        ]);
    }
}
