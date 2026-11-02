<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['category_map' => 'array', 'payment_method_map' => 'array', 'checkpoint_map' => 'array', 'applied_at' => 'datetime'];
    }
}
