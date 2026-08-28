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

    public function zoneHistory()
    {
        return $this->hasMany(MobZoneHistory::class)->latest();
    }

    /**
     * The most recently recorded paddock - mirrors Asset::currentLocation().
     */
    public function currentZone()
    {
        return $this->hasOne(MobZoneHistory::class)->latestOfMany();
    }
}
