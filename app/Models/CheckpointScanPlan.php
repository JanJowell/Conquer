<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CheckpointScanPlan extends Model
{
    protected $fillable = ['event_id', 'category_id', 'fingerprint', 'category_started_at', 'snapshot'];

    protected function casts(): array
    {
        return ['category_started_at' => 'datetime', 'snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A recorded checkpoint plan is immutable.'));
        static::deleting(fn () => throw new LogicException('Checkpoint plans require a reviewed retention process.'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
