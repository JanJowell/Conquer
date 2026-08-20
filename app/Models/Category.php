<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Category extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'GCash' => 'GCash',
        'Maya' => 'Maya',
        'Bank' => 'Bank',
    ];

    protected $fillable = [
        'event_id',
        'name',
        'distance_km',
        'type_details',
        'description',
        'slot_limit',
        'price_cents',
        'price_currency',
        'payment_provider',
        'payment_account_name',
        'payment_account_number',
        'payment_instructions',
        'status',
        'scheduled_start_date',
        'scheduled_start_time',
        'scheduled_end_date',
        'scheduled_end_time',
        'started_at',
        'started_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'type_details' => 'array',
            'slot_limit' => 'integer',
            'price_cents' => 'integer',
            'scheduled_start_date' => 'date',
            'scheduled_start_time' => 'datetime:H:i',
            'scheduled_end_date' => 'date',
            'scheduled_end_time' => 'datetime:H:i',
            'started_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function raceResults()
    {
        return $this->hasMany(RaceResult::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function scheduledStartAt(): ?Carbon
    {
        $this->loadMissing('event');

        if (! $this->event?->event_date) {
            return null;
        }

        $scheduledDate = $this->scheduled_start_date ?? $this->event->event_date;
        $scheduledTime = $this->scheduled_start_time ?? $this->event->start_time;

        if (! $scheduledDate || ! $scheduledTime) {
            return null;
        }

        return Carbon::parse(
            $scheduledDate->format('Y-m-d').' '.$scheduledTime->format('H:i:s'),
            config('app.timezone')
        );
    }

    public function scheduledEndAt(): ?Carbon
    {
        $this->loadMissing('event');

        if (! $this->event?->event_date) {
            return null;
        }

        $scheduledDate = $this->scheduled_end_date ?? $this->scheduled_start_date ?? $this->event->event_date;
        $scheduledTime = $this->scheduled_end_time ?? $this->event->end_time;

        if (! $scheduledDate || ! $scheduledTime) {
            return null;
        }

        return Carbon::parse(
            $scheduledDate->format('Y-m-d').' '.$scheduledTime->format('H:i:s'),
            config('app.timezone')
        );
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function requiresMedicalCertificate(): bool
    {
        return $this->distance_km !== null && (float) $this->distance_km >= 50.0;
    }

    public function typeDetailSchema(): array
    {
        return config("conquer.event_category_type_details.{$this->event?->interest_type}", []);
    }

    public function formattedTypeDetails(): array
    {
        return collect($this->typeDetailSchema())
            ->map(function (array $definition, string $key) {
                $value = is_array($this->type_details) ? ($this->type_details[$key] ?? null) : null;

                if (! filled($value)) {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'value' => $value.(isset($definition['suffix']) ? ' '.$definition['suffix'] : ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function distanceFromTypeDetails(?string $eventType, array $details): ?float
    {
        return match ($eventType) {
            'Triathlon' => isset($details['swim_distance_m'], $details['bike_distance_km'], $details['run_distance_km'])
                ? ((float) $details['swim_distance_m'] / 1000) + (float) $details['bike_distance_km'] + (float) $details['run_distance_km']
                : null,
            'Duathlon' => isset($details['first_run_distance_km'], $details['bike_distance_km'], $details['second_run_distance_km'])
                ? (float) $details['first_run_distance_km'] + (float) $details['bike_distance_km'] + (float) $details['second_run_distance_km']
                : null,
            default => null,
        };
    }

    public static function paymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public static function normalizePaymentProvider(?string $provider): ?string
    {
        if (blank($provider)) {
            return null;
        }

        $normalizedProvider = strtolower(trim($provider));

        return [
            'gcash' => 'GCash',
            'g-cash' => 'GCash',
            'maya' => 'Maya',
            'paymaya' => 'Maya',
            'bank' => 'Bank',
            'bank transfer' => 'Bank',
        ][$normalizedProvider] ?? $provider;
    }

    public function getPaymentProviderLabelAttribute(): ?string
    {
        $provider = self::normalizePaymentProvider($this->payment_provider);

        return self::PAYMENT_METHODS[$provider] ?? $provider;
    }
}
