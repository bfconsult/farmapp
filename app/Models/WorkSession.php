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
        'zone_id',
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

    public function zone()
    {
        return $this->belongsTo(Zone::class);
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

    /**
     * Finalised/approved activity for a property over a date range, grouped
     * by day - the shared shape behind both the public diary share
     * (DiaryShareController) and the in-app approver diary (ReportController).
     * Curated: no billing figures, only what's needed to recognise the work.
     */
    public static function diaryDays(int $propertyId, $dateFrom, $dateTo)
    {
        return static::where('property_id', $propertyId)
            ->whereIn('status', [self::FINALISED, self::APPROVED])
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with(['farmJob', 'user', 'photos'])
            ->orderBy('started_at')
            ->get()
            ->groupBy(fn ($session) => $session->started_at->toDateString())
            ->map(fn ($daySessions, $date) => [
                'date' => $date,
                'entries' => $daySessions->map(fn ($session) => [
                    'id' => $session->id,
                    'user_name' => $session->user->name,
                    'label' => $session->farmJob?->name
                        ?? ($session->source === 'auto_tracked' ? 'Auto-tracked visit' : 'Ad-hoc work'),
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'duration_in_hours' => $session->duration_in_hours,
                    'description' => $session->description,
                    'photos' => $session->photos->map(fn ($photo) => [
                        'id' => $photo->id,
                        'url' => $photo->url,
                    ]),
                ])->values(),
            ])
            ->sortKeys()
            ->values();
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
