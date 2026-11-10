<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CheckpointScan extends Model
{
    protected $fillable = [
        'checkpoint_scan_plan_id', 'event_id', 'category_id', 'registration_id', 'checkpoint_id',
        'scanned_by_user_id', 'scanned_by_name', 'client_scan_id', 'request_fingerprint',
        'participant_name', 'bib_number', 'checkpoint_label', 'checkpoint_position', 'scanned_at',
        'elapsed_seconds', 'after_cutoff',
    ];

    protected $hidden = ['request_fingerprint'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime', 'elapsed_seconds' => 'integer',
            'checkpoint_position' => 'integer', 'after_cutoff' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Checkpoint visits are append-only.'));
        static::deleting(fn () => throw new LogicException('Checkpoint visits require a reviewed retention process.'));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CheckpointScanPlan::class, 'checkpoint_scan_plan_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(CategoryCourseCheckpoint::class, 'checkpoint_id');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}
