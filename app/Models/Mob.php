<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mob extends Model
{
    protected $fillable = ['property_id', 'created_by', 'name'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function livestock()
    {
        return $this->hasMany(Livestock::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class)->latest();
    }

    /**
     * Ordered by the effective move date, not when the row was entered -
     * moved_at can be backdated, so created_at is only the tiebreaker for
     * entries on the same day.
     */
    public function zoneHistory()
    {
        return $this->hasMany(MobZoneHistory::class)->orderByDesc('moved_at')->orderByDesc('created_at');
    }

    /**
     * The most recently effective paddock - mirrors Asset::currentLocation().
     */
    public function currentZone()
    {
        return $this->hasOne(MobZoneHistory::class)->latestOfMany(['moved_at', 'created_at']);
    }
}
