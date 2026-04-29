<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'venue',
        'event_date',
        'start_time',
        'end_time',
        'registration_deadline',
        'status',
        'banner_image',
        'organized_by',
        'interest_type',
        'manager_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'registration_deadline' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            $event->status = static::statusForDate($event->status, $event->event_date);
        });
    }

    public static function statusForDate(?string $status, mixed $eventDate): ?string
    {
        if ($status === 'upcoming' && $eventDate && Carbon::parse($eventDate)->isBefore(today())) {
            return 'completed';
        }

        return $status;
    }

    public function getEffectiveStatusAttribute(): string
    {
        return static::statusForDate($this->status, $this->event_date) ?? 'upcoming';
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function raceResults()
    {
        return $this->hasMany(RaceResult::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
