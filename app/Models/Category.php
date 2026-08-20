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
        'description',
        'slot_limit',
        'price_cents',
        'price_currency',
        'payment_provider',
        'payment_account_name',
        'payment_account_number',
        'payment_instructions',
        'status',
        'scheduled_start_time',
        'scheduled_end_time',
        'started_at',
        'started_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'slot_limit' => 'integer',
            'price_cents' => 'integer',
            'scheduled_start_time' => 'datetime:H:i',
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

        $scheduledTime = $this->scheduled_start_time ?? $this->event->start_time;

        if (! $scheduledTime) {
            return null;
        }

        return Carbon::parse(
            $this->event->event_date->format('Y-m-d').' '.$scheduledTime->format('H:i:s'),
            config('app.timezone')
        );
    }

    public function scheduledEndAt(): ?Carbon
    {
        $this->loadMissing('event');

        if (! $this->event?->event_date) {
            return null;
        }

        $scheduledTime = $this->scheduled_end_time ?? $this->event->end_time;

        if (! $scheduledTime) {
            return null;
        }

        return Carbon::parse(
            $this->event->event_date->format('Y-m-d').' '.$scheduledTime->format('H:i:s'),
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
