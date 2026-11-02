<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $attributes = ['review_status' => 'draft', 'review_version' => 0];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'venue',
        'venue_id',
        'event_date',
        'event_end_date',
        'start_time',
        'end_time',
        'registration_deadline',
        'status',
        'banner_image',
        'organized_by',
        'interest_type',
        'type_details',
        'payment_setup_needs_review',
        'manager_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'event_end_date' => 'date',
            'registration_deadline' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'type_details' => 'array',
            'payment_setup_needs_review' => 'boolean',
            'review_version' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if (! $event->isReviewPublished()) {
                $event->status = 'draft';
            }
            $event->status = static::statusForDate(
                $event->status,
                $event->event_date,
                $event->start_time,
                $event->end_time,
                $event->event_end_date
            );
        });

        static::created(function (Event $event) {
            $event->syncAutomaticAnnouncement();
        });
    }

    public static function statusForDate(
        ?string $status,
        mixed $eventDate,
        mixed $startTime = null,
        mixed $endTime = null,
        mixed $eventEndDate = null
    ): ?string {
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

        $timezone = config('app.timezone');
        $eventDay = Carbon::parse($eventDate, $timezone)->startOfDay();
        $eventEndDay = Carbon::parse($eventEndDate ?: $eventDate, $timezone)->startOfDay();
        $startAt = $startTime
            ? static::dateTimeForEvent($eventDay, $startTime)
            : $eventDay;

        if (now($timezone)->lt($startAt)) {
            return 'upcoming';
        }

        $endAt = $endTime
            ? static::dateTimeForEvent($eventEndDay, $endTime)
            : $eventEndDay->copy()->endOfDay();

        if (now($timezone)->gte($endAt)) {
            return 'completed';
        }

        return 'ongoing';
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->automaticStatus();
    }

    public function automaticStatus(array $overrides = [], bool $hasBannerUpload = false): string
    {
        if (! $this->isReviewPublished()) {
            return 'draft';
        }
        if ($this->publicReadinessErrors($overrides, $hasBannerUpload) !== []) {
            return 'draft';
        }

        return static::statusForDate(
            'upcoming',
            $overrides['event_date'] ?? $this->event_date,
            $overrides['start_time'] ?? $this->start_time,
            $overrides['end_time'] ?? $this->end_time,
            $overrides['event_end_date'] ?? $this->event_end_date
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

        $typeDetails = array_key_exists('type_details', $overrides)
            ? $overrides['type_details']
            : $this->type_details;
        $interestType = $overrides['interest_type'] ?? $this->interest_type;

        // Only pre-workflow events can retain the old unstructured contract.
        // New submissions and revisions must not bypass required fields by omission.
        if ($typeDetails !== null || $this->review_status !== null) {
            $typeDetails = is_array($typeDetails) ? $typeDetails : [];
            foreach (static::typeDetailSchema($interestType) as $key => $definition) {
                if (! ($definition['required_for_publication'] ?? false)) {
                    continue;
                }

                if (! array_key_exists($key, $typeDetails) || ($definition['type'] !== 'boolean' && blank($typeDetails[$key]))) {
                    $errors[] = 'add '.strtolower($definition['label']);
                }
            }
        }

        if (! $this->exists || ! $this->categories()->where('status', 'open')->exists()) {
            $errors[] = 'add at least one open category';
        }

        $categoryDetailSchema = config("conquer.event_category_type_details.{$interestType}", []);
        $requiredCategoryDetails = collect($categoryDetailSchema)
            ->filter(fn (array $definition) => $definition['required_for_publication'] ?? false);

        if ($requiredCategoryDetails->isNotEmpty() && $this->exists) {
            $openCategories = $this->categories()->where('status', 'open')->with('event')->get();

            foreach ($requiredCategoryDetails as $key => $definition) {
                if ($openCategories->contains(fn (Category $category) => blank($category->resolvedTypeDetails()[$key] ?? null))) {
                    $errors[] = 'add '.strtolower($definition['label']).' to every open category';
                }
            }
        }

        $paidOpenCategories = $this->categories()
            ->where('status', 'open')
            ->where('price_cents', '>', 0)
            ->get();

        if ($paidOpenCategories->isNotEmpty() && ! $this->hasUsablePaymentOptions($paidOpenCategories)) {
            $errors[] = 'add at least one enabled event payment option';
        }

        return $errors;
    }

    public static function typeDetailSchema(?string $interestType): array
    {
        return config("conquer.event_type_details.{$interestType}", []);
    }

    public function categorySectionLabel(): string
    {
        return config("conquer.event_category_labels.{$this->interest_type}", 'Registration Categories');
    }

    public function formattedTypeDetails(): array
    {
        return collect(static::typeDetailSchema($this->interest_type))
            ->map(function (array $definition, string $key) {
                if (! is_array($this->type_details) || ! array_key_exists($key, $this->type_details)) {
                    return null;
                }

                $value = $this->type_details[$key];

                if ($definition['type'] === 'boolean') {
                    $value = $value ? 'Yes' : 'No';
                } elseif (filled($value) && isset($definition['suffix'])) {
                    $value .= ' '.$definition['suffix'];
                }

                return ['key' => $key, 'label' => $definition['label'], 'value' => $value];
            })
            ->filter(fn ($item) => $item !== null && filled($item['value']))
            ->values()
            ->all();
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

    public function paymentMethods()
    {
        return $this->hasMany(EventPaymentMethod::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function enabledPaymentMethods()
    {
        return $this->paymentMethods()->where('is_enabled', true);
    }

    public function hasUsablePaymentOptions($paidCategories = null): bool
    {
        $eventMethods = $this->paymentMethods()->get();

        if ($eventMethods->isNotEmpty()) {
            return $eventMethods->contains(fn (EventPaymentMethod $method) => $method->is_enabled
                && ($method->isOnlineCheckout()
                    || (filled($method->account_name) && (filled($method->account_number) || filled($method->instructions)))));
        }

        // Temporary compatibility for events created before event-owned payment options.
        $paidCategories ??= $this->categories()
            ->where('status', 'open')
            ->where('price_cents', '>', 0)
            ->get();

        return $paidCategories->isNotEmpty() && $paidCategories->every(fn (Category $category) => filled($category->payment_provider)
            && filled($category->payment_account_name)
            && (filled($category->payment_account_number) || filled($category->payment_instructions))
        );
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function currentUserRegistration()
    {
        return $this->hasOne(Registration::class);
    }

    public function currentUserRegistrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function raceResults()
    {
        return $this->hasMany(RaceResult::class);
    }

    public function finishScans()
    {
        return $this->hasMany(FinishScan::class);
    }

    public function registrationFeedback()
    {
        return $this->hasMany(RegistrationFeedback::class);
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

        $isPublished = $this->isReviewPublished() && $this->publicReadinessErrors() === [];
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
        $eventEndDate = $this->event_end_date
            ? $this->event_end_date->format('F j, Y')
            : $eventDate;
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
            ($eventEndDate !== $eventDate ? 'Event dates: ' : 'Event date: ')
                .$eventDate.($eventEndDate !== $eventDate ? ' - '.$eventEndDate : ''),
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

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function savedVenue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function isReviewPublished(): bool
    {
        return ! $this->revision_of_id && in_array($this->review_status, [null, 'approved'], true);
    }

    public function canEditSetup(User $user): bool
    {
        return $user->managesAssignedEventsOnly() && $user->canManageEvent($this)
            && (in_array($this->review_status, ['draft', 'changes_requested'], true)
                || ($this->review_status === null && $this->effective_status === 'draft'));
    }

    public function scopeVisibleToStaff(Builder $query, User $user): void
    {
        if ($user->managesAssignedEventsOnly()) {
            $query->where($query->qualifyColumn('manager_id'), $user->id);
        } elseif ($user->isSuperAdmin()) {
            $query->where(fn (Builder $inner) => $inner->whereNull($query->qualifyColumn('revision_of_id'))
                ->orWhereIn($query->qualifyColumn('review_status'), ['pending_review', 'approved']));
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'revision_of_id');
    }

    public function activeRevision()
    {
        return $this->hasOne(self::class, 'revision_of_id')
            ->whereIn('review_status', ['draft', 'changes_requested', 'pending_review'])->latest('id');
    }

    public function reviewLabel(): string
    {
        return match ($this->review_status) {
            null => 'Existing event (pre-review workflow)',
            'pending_review' => 'Pending Review',
            'changes_requested' => 'Changes Requested',
            'discarded' => 'Discarded Revision',
            'approved' => $this->revision_of_id ? 'Revision Published' : 'Approved & Published',
            default => $this->revision_of_id ? 'Draft revision' : 'Draft',
        };
    }

    public function reviews()
    {
        return $this->hasMany(EventReview::class)->latest('id');
    }
}
