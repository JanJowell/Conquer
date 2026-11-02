<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submission_snapshot' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
