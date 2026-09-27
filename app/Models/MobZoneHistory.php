<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobZoneHistory extends Model
{
    // Eloquent would otherwise guess "mob_zone_histories" (pluralizing
    // "history" as if it were a countable noun in this context).
    protected $table = 'mob_zone_history';

    protected $fillable = ['mob_id', 'zone_id', 'moved_at', 'created_by'];

    protected $casts = [
        'moved_at' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        // Defaults to today so every existing call site (mob creation, the
        // move form) keeps working unchanged - only the new date-edit UI
        // needs to pass moved_at explicitly.
        static::creating(function (MobZoneHistory $history) {
            $history->moved_at ??= now()->toDateString();
        });
    }

    public function mob()
    {
        return $this->belongsTo(Mob::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
