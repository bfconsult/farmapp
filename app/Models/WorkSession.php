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
        'asset_id',
        'zone_id',
        'user_id',
        'created_by',
        'description',
        'started_at',
        'ended_at',
        'latitude',
        'longitude',
        'status',
        'source',
        'external_uuid',
        'reviewed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }

    /**
     * Direct link for ad-hoc work not tied to a job - a session reached via
     * farmJob->asset already counts toward an asset's time; this covers
     * sessions with no job at all. See Asset::workSessions().
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who entered this session, when that isn't the person it's booked
     * against - set when a manager/admin/approver logs time on behalf of
     * someone else. Null for the normal self-entered case.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
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

    /**
     * Whether [$startedAt, $endedAt) collides with any of this user's other
     * sessions. Static so it can be asked *before* a session is saved - an
     * instance method can't do this on an unsaved model, since a
     * `where('id', '!=', $this->id)` guard compares against null and MySQL
     * treats `id != NULL` as unknown, matching zero rows (the check would
     * silently always pass). Strict inequalities so two sessions that just
     * touch end-to-end (one starts the instant the other ends) don't count
     * as overlapping. A still-open session ($endedAt null on the OTHER
     * session) counts as open-ended: anything that starts after it began
     * always collides with it.
     */
    public static function overlapExistsFor(int $userId, $startedAt, $endedAt, ?int $ignoreId = null, ?array $statuses = null): bool
    {
        if (!$endedAt) {
            return false;
        }

        return static::where('user_id', $userId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->where('started_at', '<', $endedAt)
            ->where(fn ($q) => $q->where('ended_at', '>', $startedAt)->orWhereNull('ended_at'))
            ->exists();
    }

    /**
     * Whether this session's time range overlaps another finalised/approved
     * session belonging to the same user - a worker can't genuinely be doing
     * two things at once, so finalising both would double-count hours and
     * billing.
     */
    public function overlapsFinalisedSession(): bool
    {
        return static::overlapExistsFor(
            $this->user_id, $this->started_at, $this->ended_at,
            $this->id, [self::FINALISED, self::APPROVED]
        );
    }
}
