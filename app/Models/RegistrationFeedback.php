<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationFeedback extends Model
{
    use HasFactory;

    public const EDIT_WINDOW_DAYS = 7;

    protected $table = 'registration_feedback';

    protected $fillable = [
        'registration_id',
        'user_id',
        'event_id',
        'category_id',
        'overall_rating',
        'organization_rating',
        'route_rating',
        'safety_rating',
        'experience_rating',
        'comment',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'organization_rating' => 'integer',
            'route_rating' => 'integer',
            'safety_rating' => 'integer',
            'experience_rating' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function editableUntil()
    {
        return $this->submitted_at?->copy()->addDays(self::EDIT_WINDOW_DAYS);
    }

    public function canEdit(): bool
    {
        return $this->editableUntil()?->isFuture() ?? false;
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
