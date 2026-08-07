<?php

namespace App\Console\Commands;

use App\Models\CommunityPost;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeArchivedCommunityPosts extends Command
{
    protected $signature = 'community-posts:purge-archived
        {--days=30 : Permanently delete posts archived for at least this many days}
        {--dry-run : Report the number of eligible posts without deleting them}';

    protected $description = 'Permanently delete expired archived community posts and their media.';

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
        $eligibleCount = $this->expiredPostsQuery($cutoff)->count();
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info("Dry run: {$eligibleCount} archived community post(s) are eligible for permanent deletion.");
            $this->writeLog($days, $cutoff, $eligibleCount, 0, true);

            return self::SUCCESS;
        }

        $deletedCount = 0;

        $this->expiredPostsQuery($cutoff)
            ->select('community_posts.id')
            ->chunkById(100, function ($posts) use ($cutoff, &$deletedCount) {
                foreach ($posts as $post) {
                    $deleted = DB::transaction(function () use ($post, $cutoff): bool {
                        $expiredPost = $this->expiredPostsQuery($cutoff)
                            ->whereKey($post->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $expiredPost) {
                            return false;
                        }

                        return (bool) $expiredPost->forceDelete();
                    }, 3);

                    if ($deleted) {
                        $deletedCount++;
                    }
                }
            });

        $skippedCount = max(0, $eligibleCount - $deletedCount);

        $this->info("Permanently deleted {$deletedCount} archived community post(s).");
        $this->writeLog($days, $cutoff, $eligibleCount, $deletedCount, false, $skippedCount);

        return self::SUCCESS;
    }

    private function expiredPostsQuery(CarbonInterface $cutoff)
    {
        return CommunityPost::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff);
    }

    private function writeLog(
        int $days,
        CarbonInterface $cutoff,
        int $eligibleCount,
        int $deletedCount,
        bool $dryRun,
        int $skippedCount = 0,
    ): void {
        Log::info('Archived community post cleanup completed.', [
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'eligible_count' => $eligibleCount,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'dry_run' => $dryRun,
        ]);
    }
}
