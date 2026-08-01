<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'content',
        'is_published',
        'is_auto_generated',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_auto_generated' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->is_published
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

}
