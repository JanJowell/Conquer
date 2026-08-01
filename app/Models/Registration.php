<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'category_id',
        'bib_number',
        'shirt_size',
        'medical_conditions',
        'medical_certificate_path',
        'medical_certificate_submitted_at',
        'first_aid_kit_confirmed',
        'waiver_accepted',
        'waiver_accepted_at',
        'waiver_name',
        'waiver_ip',
        'waiver_user_agent',
        'kit_waiver_signed_at',
        'kit_released_at',
        'status',
        'rejection_reason',
        'payment_required',
        'payment_status',
        'payment_amount_cents',
        'payment_currency',
        'paid_at',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_required' => 'boolean',
            'payment_amount_cents' => 'integer',
            'first_aid_kit_confirmed' => 'boolean',
            'waiver_accepted' => 'boolean',
            'medical_certificate_submitted_at' => 'datetime',
            'waiver_accepted_at' => 'datetime',
            'kit_waiver_signed_at' => 'datetime',
            'kit_released_at' => 'datetime',
            'paid_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
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

    public function raceResult()
    {
        return $this->hasOne(RaceResult::class);
    }

    public function issuedEBadges()
    {
        return $this->hasMany(IssuedEBadge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
