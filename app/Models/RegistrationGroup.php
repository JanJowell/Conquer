<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationGroup extends Model
{
    use HasFactory;

    public const STATUS_FORMING = 'forming';

    public const STATUS_READY = 'ready';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'event_id',
        'category_id',
        'leader_user_id',
        'name',
        'join_code_hash',
        'status',
        'locked_at',
        'expires_at',
    ];

    protected $hidden = ['join_code_hash'];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function activeRegistrations()
    {
        return $this->registrations()->where('status', '!=', 'rejected');
    }
}
