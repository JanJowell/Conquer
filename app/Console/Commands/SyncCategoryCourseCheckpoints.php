<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\CategoryCourseCheckpointSync;
use Illuminate\Console\Command;

class SyncCategoryCourseCheckpoints extends Command
{
    protected $signature = 'course-checkpoints:sync {--category=* : Limit to category IDs} {--write : Explicitly apply the previewed changes}';

    protected $description = 'Preview derived category checkpoint identities from saved routes; no writes unless --write is supplied';

    public function handle(CategoryCourseCheckpointSync $sync): int
    {
        if (! (new Category)->getConnection()->getSchemaBuilder()->hasTable('category_course_checkpoints')) {
            $this->error('Apply the category course checkpoint migration before using this command.');

            return self::FAILURE;
        }
        $ids = $this->option('category');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Category IDs must be positive integers.');

                return self::FAILURE;
            }
        }
        $totals = ['categories' => 0, 'created' => 0, 'updated' => 0, 'retired' => 0, 'invalid' => 0];
        Category::query()->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->where(fn ($query) => $query->whereNotNull('course_map')->orWhereHas('courseCheckpoints'))
            ->chunkById(100, function ($categories) use ($sync, &$totals): void {
                foreach ($categories as $category) {
                    $result = $sync->sync($category, dryRun: ! $this->option('write'));
                    $totals['categories']++;
                    $totals['invalid'] += (int) ! $result['valid'];
                    foreach (['created', 'updated', 'retired'] as $key) {
                        $totals[$key] += $result[$key];
                    }
                }
            });
        $this->info(($this->option('write') ? 'Applied' : 'Preview only; no writes').': '.
            implode(', ', array_map(fn ($key, $value) => $key.'='.$value, array_keys($totals), $totals)));
        if ($totals['invalid'] > 0) {
            $this->warn('Invalid legacy routes were skipped without changing their identities.');
        }

        return $totals['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
