<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobZoneHistory extends Model
{
    // Eloquent would otherwise guess "mob_zone_histories" (pluralizing
    // "history" as if it were a countable noun in this context).
    protected $table = 'mob_zone_history';

    protected $fillable = ['mob_id', 'zone_id', 'created_by'];

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
