<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
        'deleted_by_user_id',
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

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (CommunityPost $post) {
            $paths = array_values(array_filter([
                $post->image_path,
                $post->video_path,
            ]));

            if ($paths !== []) {
                Storage::disk('public')->delete($paths);
            }
        });
    }
}
