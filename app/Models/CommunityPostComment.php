<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPostComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'community_post_id',
        'user_id',
        'content',
        'is_flagged',
        'moderation_note',
        'moderated_by',
        'moderated_at',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'moderated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
