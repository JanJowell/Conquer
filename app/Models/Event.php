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
            $event->status = static::statusForDate($event->status, $event->event_date, $event->start_time, $event->end_time);
        });

        static::created(function (Event $event) {
            $event->syncAutomaticAnnouncement();
        });
    }

    public static function statusForDate(?string $status, mixed $eventDate, mixed $startTime = null, mixed $endTime = null): ?string
    {
        if ($status === 'draft') {
            return 'draft';
        }

        if ($status === 'archived') {
            return 'completed';
        }

        if ($status === 'published') {
            $status = 'upcoming';
        }

        if ($status === 'completed') {
            return 'completed';
        }

        if (! $eventDate) {
            return $status ?: 'upcoming';
        }

        $eventDay = Carbon::parse($eventDate)->startOfDay();

        if ($eventDay->isFuture()) {
            return 'upcoming';
        }

        if ($eventDay->isPast()) {
            return 'completed';
        }

        if (! $startTime) {
            return 'ongoing';
        }

        $startAt = static::dateTimeForEvent($eventDay, $startTime);

        if (now()->lt($startAt)) {
            return 'upcoming';
        }

        if ($endTime) {
            $endAt = static::dateTimeForEvent($eventDay, $endTime);

            if (now()->gt($endAt)) {
                return 'completed';
            }
        }

        return 'ongoing';
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->automaticStatus();
    }

    public function automaticStatus(array $overrides = [], bool $hasBannerUpload = false): string
    {
        if ($this->publicReadinessErrors($overrides, $hasBannerUpload) !== []) {
            return 'draft';
        }

        return static::statusForDate(
            'upcoming',
            $overrides['event_date'] ?? $this->event_date,
            $overrides['start_time'] ?? $this->start_time,
            $overrides['end_time'] ?? $this->end_time
        ) ?? 'upcoming';
    }

    public function refreshAutomaticStatus(array $overrides = [], bool $hasBannerUpload = false): bool
    {
        $status = $this->automaticStatus($overrides, $hasBannerUpload);

        if ($this->status === $status) {
            $this->syncAutomaticAnnouncement();

            return true;
        }

        $saved = $this->forceFill(['status' => $status])->save();

        if ($saved) {
            $this->syncAutomaticAnnouncement();
        }

        return $saved;
    }

    public function publicReadinessErrors(array $overrides = [], bool $hasBannerUpload = false): array
    {
        $requiredFields = [
            'title' => 'add an event name',
            'description' => 'add a description',
            'venue' => 'add a venue',
            'event_date' => 'set an event date',
            'start_time' => 'set a start time',
            'registration_deadline' => 'set a registration deadline',
            'organized_by' => 'add the organizer',
            'interest_type' => 'choose an event type',
            'banner_image' => 'upload a banner image',
        ];

        $errors = [];

        foreach ($requiredFields as $field => $message) {
            if ($field === 'banner_image' && $hasBannerUpload) {
                continue;
            }

            $value = array_key_exists($field, $overrides) ? $overrides[$field] : $this->{$field};

            if (blank($value)) {
                $errors[] = $message;
            }
        }

        if (! $this->exists || ! $this->categories()->where('status', 'open')->exists()) {
            $errors[] = 'add at least one open category';
        }

        $incompletePaidCategories = $this->categories()
            ->where('status', 'open')
            ->where('price_cents', '>', 0)
            ->get()
            ->filter(fn ($category) => blank($category->payment_provider)
                || blank($category->payment_account_name)
                || (blank($category->payment_account_number) && blank($category->payment_instructions)))
            ->pluck('name')
            ->values();

        if ($incompletePaidCategories->isNotEmpty()) {
            $errors[] = 'complete payment details for paid open categories: '.$incompletePaidCategories->join(', ');
        }

        return $errors;
    }

    private static function dateTimeForEvent(Carbon $eventDay, mixed $time): Carbon
    {
        $time = $time instanceof Carbon ? $time->format('H:i:s') : Carbon::parse($time)->format('H:i:s');

        return Carbon::parse($eventDay->format('Y-m-d').' '.$time, config('app.timezone'));
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function currentUserRegistration()
    {
        return $this->hasOne(Registration::class);
    }

    public function raceResults()
    {
        return $this->hasMany(RaceResult::class);
    }

    public function eBadges()
    {
        return $this->hasMany(EBadge::class);
    }

    public function issuedEBadges()
    {
        return $this->hasMany(IssuedEBadge::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function syncAutomaticAnnouncement(): ?Announcement
    {
        if (! $this->exists) {
            return null;
        }

        $announcement = $this->announcements()
            ->where('is_auto_generated', true)
            ->first();

        $isPublished = $this->publicReadinessErrors() === [];
        $publishedAt = $isPublished
            ? ($announcement?->published_at ?? now())
            : null;

        return $this->announcements()->updateOrCreate(
            ['is_auto_generated' => true],
            [
                'title' => 'Registration now open: '.$this->title,
                'content' => $this->automaticAnnouncementContent(),
                'is_published' => $isPublished,
                'published_at' => $publishedAt,
                'expires_at' => $this->automaticAnnouncementExpiresAt(),
            ]
        );
    }

    private function automaticAnnouncementContent(): string
    {
        $eventDate = $this->event_date
            ? $this->event_date->format('F j, Y')
            : 'To be announced';
        $startTime = $this->start_time
            ? Carbon::parse($this->start_time)->format('g:i A')
            : 'To be announced';
        $endTime = $this->end_time
            ? Carbon::parse($this->end_time)->format('g:i A')
            : null;
        $registrationDeadline = $this->registration_deadline
            ? $this->registration_deadline->format('F j, Y')
            : 'To be announced';

        $lines = [
            $this->title,
            '',
            'Registration deadline: '.$registrationDeadline,
            'Event date: '.$eventDate,
            'Event time: '.$startTime.($endTime ? ' - '.$endTime : ''),
        ];

        if (filled($this->venue)) {
            $lines[] = 'Venue: '.$this->venue;
        }

        return implode("\n", $lines);
    }

    private function automaticAnnouncementExpiresAt(): ?\DateTimeInterface
    {
        if (! $this->registration_deadline) {
            return null;
        }

        return $this->registration_deadline
            ->copy()
            ->endOfDay()
            ->addHour();
    }

    public function checkpoints()
    {
        return $this->hasMany(Checkpoint::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
