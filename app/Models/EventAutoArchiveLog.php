<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// System audit evidence, separate from logs that require a human Admin actor.
class EventAutoArchiveLog extends Model
{
    protected $fillable = ['event_id', 'event_title', 'event_ended_at', 'cutoff_at', 'archived_at'];

    protected function casts(): array
    {
        return ['event_ended_at' => 'datetime', 'cutoff_at' => 'datetime', 'archived_at' => 'datetime'];
    }
}
