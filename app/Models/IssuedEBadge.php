<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssuedEBadge extends Model
{
    use HasFactory;

    protected $table = 'issued_e_badges';

    protected $fillable = [
        'e_badge_id',
        'registration_id',
        'user_id',
        'event_id',
        'issued_by',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function badge()
    {
        return $this->belongsTo(EBadge::class, 'e_badge_id');
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

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
