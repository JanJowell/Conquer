<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPaymentMethod extends Model
{
    use HasFactory;

    public const PROVIDERS = [
        'GCash' => 'GCash',
        'Maya' => 'Maya',
        'Bank' => 'Bank Transfer',
        'PayMongo' => 'PayMongo Online Checkout',
    ];

    protected $fillable = [
        'event_id',
        'provider',
        'account_name',
        'account_number',
        'instructions',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function isOnlineCheckout(): bool
    {
        return $this->provider === 'PayMongo';
    }

    public static function providers(): array
    {
        return self::PROVIDERS;
    }
}
