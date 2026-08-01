<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'title',
        'content',
        'image_path',
        'video_path',
        'is_flagged',
        'moderation_note',
        'moderated_by',
        'moderated_at',
        'created_at',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'moderated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function comments()
    {
        return $this->hasMany(CommunityPostComment::class);
    }

    public function likes()
    {
        return $this->hasMany(CommunityPostLike::class);
    }

    public function hides()
    {
        return $this->hasMany(CommunityPostHidden::class);
    }

    public function reports()
    {
        return $this->hasMany(CommunityPostReport::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
