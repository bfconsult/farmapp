<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSession extends Model
{
    const DRAFT = 'draft';
    const FINALISED = 'finalised';
    const APPROVED = 'approved';

    const STATUSES = [self::DRAFT, self::FINALISED, self::APPROVED];

    protected $fillable = [
        'property_id',
        'farm_job_id',
        'user_id',
        'description',
        'started_at',
        'ended_at',
        'latitude',
        'longitude',
        'status',
        'source',
        'external_uuid',
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

    public function waypoints()
    {
        return $this->hasMany(WorkSessionWaypoint::class)->orderBy('recorded_at');
    }

    public function getDurationInHoursAttribute()
    {
        if (!$this->ended_at) return null;

        $minutes = $this->started_at->diffInMinutes($this->ended_at);

        if ($blockMinutes = $this->user?->billing_block_minutes) {
            $minutes = ceil($minutes / $blockMinutes) * $blockMinutes;
        }

        return round($minutes / 60, 2);
    }

    /**
     * A job's own hourly_rate overrides the worker's default rate when set.
     */
    public function getHourlyRateAttribute()
    {
        return $this->farmJob?->hourly_rate ?? $this->user?->hourly_rate;
    }

    public function getBillingAmountAttribute()
    {
        if (!$this->duration_in_hours || !$this->hourly_rate) return null;
        return round($this->duration_in_hours * $this->hourly_rate, 2);
    }
}
