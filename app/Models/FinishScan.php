<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishScan extends Model
{
    use HasFactory;

    public const STATUS_PROVISIONAL = 'provisional';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'registration_id',
        'user_id',
        'event_id',
        'category_id',
        'bib_number',
        'scanned_at',
        'elapsed_seconds',
        'elapsed_time',
        'scanned_by_user_id',
        'client_scan_id',
        'captured_offline',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'elapsed_seconds' => 'integer',
            'captured_offline' => 'boolean',
            'published_at' => 'datetime',
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

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}
