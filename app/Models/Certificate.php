<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'user_id',
        'event_id',
        'category_id',
        'certificate_number',
        'verification_token',
        'participant_name',
        'event_title',
        'category_name',
        'distance_km',
        'event_date',
        'venue',
        'finish_time',
        'rank_overall',
        'rank_category',
        'issued_by',
        'issued_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'event_date' => 'date',
            'rank_overall' => 'integer',
            'rank_category' => 'integer',
            'distance_km' => 'decimal:2',
        ];
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

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }
}
