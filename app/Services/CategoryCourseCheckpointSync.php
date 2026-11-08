<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryCourseCheckpoint;
use App\Rules\CategoryCourseMap;

class CategoryCourseCheckpointSync
{
    /**
     * Reconcile derived records without changing the category's route or review
     * snapshot. HTTP category writes already run inside their owning transaction.
     * Raw SQL/bulk updates bypass model events and require the explicit command.
     *
     * @return array{ready: bool, valid: bool, created: int, updated: int, retired: int}
     */
    public function sync(Category $category, bool $dryRun = false): array
    {
        $result = ['ready' => true, 'valid' => true, 'created' => 0, 'updated' => 0, 'retired' => 0];
        $connection = $category->getConnection();
        if (! $connection->getSchemaBuilder()->hasTable('category_course_checkpoints')) {
            // Allow the existing forms to operate before this additive migration
            // is applied. No scanner endpoints depend on these records yet.
            return [...$result, 'ready' => false];
        }

        return $connection->transaction(function () use ($category, $connection, $dryRun, $result) {
            $query = Category::on($connection->getName())->whereKey($category->getKey());
            $current = ($dryRun ? $query : $query->lockForUpdate())->firstOrFail();
            $source = $current->getRawOriginal('course_map');
            $valid = true;
            (new CategoryCourseMap)->validate('course_map', $source, function () use (&$valid): void {
                $valid = false;
            });
            if (! $valid) {
                // Never retire good identities merely because legacy JSON is bad.
                return [...$result, 'valid' => false];
            }
            $points = CategoryCourseMap::normalize($source)['checkpoints'] ?? [];
            $records = CategoryCourseCheckpoint::on($connection->getName())
                ->where('category_id', $current->id)->active()->orderBy('position')->get();
            $remaining = $records->keyBy('id')->all();
            $matches = [];

            // Exact points first, including labels. Identical repeated entries
            // retain occurrence order only when their multiplicity is unchanged.
            foreach ($points as $index => $point) {
                $key = $this->pointKey($point);
                $old = $records->filter(fn ($row) => $this->pointKey($this->point($row)) === $key);
                $count = count(array_filter($points, fn ($candidate) => $this->pointKey($candidate) === $key));
                if ($old->count() !== $count) {
                    continue;
                }
                foreach ($old as $row) {
                    if (isset($remaining[$row->id])) {
                        $matches[$index] = $row;
                        unset($remaining[$row->id]);
                        break;
                    }
                }
            }

            // A unique unchanged location can be renamed. Do not guess identity
            // from array position, label alone, or ambiguous repeated locations.
            foreach ($points as $index => $point) {
                if (isset($matches[$index])) {
                    continue;
                }
                $key = $this->coordinateKey($point);
                $candidates = array_filter($remaining, fn ($row) => $this->coordinateKey($this->point($row)) === $key);
                $unmatched = array_filter(array_keys($points), fn ($i) => ! isset($matches[$i]) && $this->coordinateKey($points[$i]) === $key);
                if (count($candidates) === 1 && count($unmatched) === 1) {
                    $row = reset($candidates);
                    $matches[$index] = $row;
                    unset($remaining[$row->id]);
                }
            }

            $result['retired'] = count($remaining);
            foreach ($points as $index => $point) {
                $row = $matches[$index] ?? null;
                if (! $row) {
                    $result['created']++;
                } elseif ($row->position !== $index + 1 || $this->pointKey($this->point($row)) !== $this->pointKey($point)) {
                    $result['updated']++;
                }
            }
            if ($dryRun || $result['created'] + $result['updated'] + $result['retired'] === 0) {
                return $result;
            }

            // Free unique positions inside the transaction before a reorder.
            $connection->table('category_course_checkpoints')->where('category_id', $current->id)
                ->whereNull('retired_at')->update(['position' => null]);
            foreach ($remaining as $row) {
                CategoryCourseCheckpoint::on($connection->getName())->whereKey($row->id)
                    ->update(['position' => null, 'retired_at' => now()]);
            }
            foreach ($points as $index => $point) {
                $attributes = ['position' => $index + 1, 'label' => $point['label'],
                    'latitude' => $point['lat'], 'longitude' => $point['lng'], 'retired_at' => null];
                if (isset($matches[$index])) {
                    CategoryCourseCheckpoint::on($connection->getName())->whereKey($matches[$index]->id)->update($attributes);
                } else {
                    CategoryCourseCheckpoint::on($connection->getName())->create(['category_id' => $current->id, ...$attributes]);
                }
            }

            return $result;
        });
    }

    private function point(CategoryCourseCheckpoint $row): array
    {
        return ['lat' => $row->latitude, 'lng' => $row->longitude, 'label' => $row->label];
    }

    private function coordinateKey(array $point): string
    {
        $lat = (float) $point['lat'];
        $lng = (float) $point['lng'];

        return sprintf('%.6F,%.6F', $lat == 0 ? 0 : $lat, $lng == 0 ? 0 : $lng);
    }

    private function pointKey(array $point): string
    {
        return $this->coordinateKey($point).'|'.$point['label'];
    }
}
