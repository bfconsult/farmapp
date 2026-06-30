<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSession extends Model
{
    protected $fillable = [
        'property_id',
        'farm_job_id',
        'user_id',
        'description',
        'started_at',
        'ended_at',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function getDurationInHoursAttribute()
    {
        if (!$this->ended_at) return null;
        return round($this->started_at->diffInMinutes($this->ended_at) / 60, 2);
    }

    public function getBillingAmountAttribute()
    {
        if (!$this->duration_in_hours || !$this->farmJob?->hourly_rate) return null;
        return round($this->duration_in_hours * $this->farmJob->hourly_rate, 2);
    }
}
