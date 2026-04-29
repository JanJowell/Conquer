<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannedIP extends Model
{
    use HasFactory;

    protected $table = 'banned_i_ps';

    protected $fillable = [
        'ip_address',
        'reason',
        'permanent',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'permanent' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
