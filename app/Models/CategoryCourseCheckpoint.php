<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryCourseCheckpoint extends Model
{
    protected $fillable = [
        'category_id', 'position', 'label', 'latitude', 'longitude', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'retired_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at')->whereNotNull('position');
    }
}
