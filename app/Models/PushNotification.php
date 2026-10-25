<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class PushNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'data',
        'type',
        'target_audience',
        'target_user_id',
        'scheduled_at',
        'sent_at',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'target_user_id' => 'integer',
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function publicData(): array
    {
        return Arr::except($this->data ?? [], ['_firebase_delivery']);
    }

    public function deliveryError(): ?string
    {
        $error = data_get($this->data, '_firebase_delivery.error');

        return is_string($error) ? $error : null;
    }
}
