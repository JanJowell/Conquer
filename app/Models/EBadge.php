<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EBadge extends Model
{
    use HasFactory;

    protected $table = 'e_badges';

    protected $fillable = [
        'event_id',
        'category_id',
        'title',
        'description',
        'image_path',
        'criteria',
        'auto_issue_rule',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function issuedBadges()
    {
        return $this->hasMany(IssuedEBadge::class, 'e_badge_id');
    }
}
