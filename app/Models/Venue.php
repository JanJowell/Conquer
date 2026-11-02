<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'created_by', 'fingerprint', 'name', 'address', 'area_code', 'area_name',
        'city_code', 'city_name', 'barangay_code', 'barangay_name', 'reference_version', 'label',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->when($user->managesAssignedEventsOnly(), function (Builder $query) use ($user) {
            $query->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('events', fn (Builder $events) => $events->where('manager_id', $user->id));
            });
        });
    }
}
