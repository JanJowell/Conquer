<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\CategoryCourseCheckpoint;
use App\Models\CheckpointScan;
use App\Models\CheckpointScanPlan;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CheckpointHistoryObserver
{
    public function deleting(Model $record): void
    {
        $connection = $record->getConnection();
        if (! $connection->getSchemaBuilder()->hasTable('checkpoint_scans')) {
            return;
        }
        $scans = CheckpointScan::on($connection->getName());
        $plans = CheckpointScanPlan::on($connection->getName());
        $hasHistory = match (true) {
            $record instanceof Event => $plans->where('event_id', $record->id)->exists(),
            $record instanceof Category => $plans->where('category_id', $record->id)->exists(),
            $record instanceof Registration => $scans->where('registration_id', $record->id)->exists(),
            $record instanceof CategoryCourseCheckpoint => $scans->where('checkpoint_id', $record->id)->exists(),
            $record instanceof User => $scans->whereHas('registration', fn ($query) => $query->where('user_id', $record->id))->exists(),
            default => false,
        };
        if ($hasHistory) {
            throw ValidationException::withMessages([
                'checkpoint_history' => 'This record has checkpoint scan history and cannot be deleted without a reviewed retention process.',
            ]);
        }
    }
}
