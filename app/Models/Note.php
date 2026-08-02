<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'property_id',
        'asset_id',
        'job_id',
        'work_session_id',
        'created_by',
        'body',
        'latitude',
        'longitude',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function job()
    {
        return $this->belongsTo(FarmJob::class);
    }

    public function workSession()
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function views()
    {
        return $this->hasMany(NoteView::class)->orderByDesc('viewed_at');
    }

    /**
     * Never unread for the note's own author. Otherwise unread if this user
     * has never viewed it, or last viewed it before the note's most recent
     * edit - so editing a note re-flags it for everyone else.
     * Requires `views` to be eager-loaded to avoid an N+1 per note.
     */
    public function isUnreadBy(int $userId): bool
    {
        if ($this->created_by === $userId) {
            return false;
        }

        $viewedAt = $this->views->firstWhere('user_id', $userId)?->viewed_at;

        return $viewedAt === null || $viewedAt->lt($this->updated_at);
    }
}
