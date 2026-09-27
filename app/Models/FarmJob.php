<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FarmJob extends Model
{
    protected $table = 'farm_jobs';

    protected $fillable = [
        'name',
        'description',
        'estimated_hours',
        'budget',
        'hourly_rate',
        'latitude',
        'longitude',
        'priority_id',
        'job_type_id',
        'job_status_id',
        'user_id',
        'property_id',
        'recurring_job_id',
        'maintenance_item_id',
        'asset_id',
        'period_start',
        'period_end',
        'scheduled_date',
        'share_token',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'scheduled_date' => 'date',
    ];

    protected $appends = ['effective_date'];

    /**
     * The date this job shows on in the calendar view - its scheduled date
     * if it has one, otherwise the date it was created.
     */
    public function getEffectiveDateAttribute(): string
    {
        return ($this->scheduled_date ?? $this->created_at)->toDateString();
    }

    protected static function booted()
    {
        static::creating(function (FarmJob $job) {
            $job->share_token = $job->share_token ?? Str::random(40);
        });

        // Single source of truth for completed_at, since job_status_id can
        // change via more than one path (the dedicated finish() action, or
        // a plain edit's status field) - stamps it the moment a job lands on
        // the property's "finished" status, clears it if moved back off.
        static::saving(function (FarmJob $job) {
            if ($job->isDirty('job_status_id')) {
                $isFinished = $job->job_status_id
                    && JobStatus::where('id', $job->job_status_id)->where('is_finished_default', true)->exists();
                $job->completed_at = $isFinished ? ($job->completed_at ?? now()) : null;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Whether this user sees the job normally (assigned to it) rather than
     * only via its share link.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $user !== null && $this->assignees()->where('users.id', $user->id)->exists();
    }

    /**
     * Curated payload for the public share view (Jobs/SharedView.jsx) - used
     * both by the job's own share link and a supplier's quote-invite link.
     * Deliberately excludes internal figures like budget/hourly_rate/location,
     * since this is a public, unauthenticated route. Expects
     * priority/jobType/jobStatus/property/photos already eager-loaded.
     */
    public function toSharePayload(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date,
            'priority' => $this->priority?->only(['name', 'color']),
            'job_type' => $this->jobType?->only(['name', 'color']),
            'job_status' => $this->jobStatus?->only(['name', 'color']),
            'property' => $this->property?->only(['name']),
            'photos' => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url,
            ]),
        ];
    }

    public function recurringJob()
    {
        return $this->belongsTo(RecurringJob::class);
    }

    public function maintenanceItem()
    {
        return $this->belongsTo(MaintenanceItem::class);
    }

    /**
     * Direct asset link for ad-hoc work not tied to a maintenance schedule -
     * see Asset::jobs().
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class);
    }

    public function jobType()
    {
        return $this->belongsTo(JobType::class);
    }

    public function jobStatus()
    {
        return $this->belongsTo(JobStatus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function zones()
    {
        return $this->belongsToMany(Zone::class);
    }

    public function views()
    {
        return $this->hasMany(FarmJobView::class)->orderByDesc('viewed_at');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class, 'job_id');
    }

    public function workSessions()
    {
        return $this->hasMany(WorkSession::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'farm_job_user', 'farm_job_id', 'user_id')->withTimestamps();
    }

    public function reminders()
    {
        return $this->hasMany(JobReminder::class);
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class);
    }

    public function incompleteChecklists()
    {
        return $this->hasMany(Checklist::class)->where('status', Checklist::INCOMPLETE);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'job_id')->latest();
    }

    /**
     * Jobs finished during a Diary report period, each annotated with its
     * all-time total_hours/total_expenses (not scoped to the period - a
     * finished job's full history is more useful here than just what
     * happened to land in this particular window) and photos. Mirrors
     * Metric::forDiaryPeriod's role/shape.
     */
    public static function completedDuringPeriod(int $propertyId, $dateFrom, $dateTo)
    {
        return static::where('property_id', $propertyId)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->with('photos')
            ->orderBy('completed_at')
            ->get()
            ->each(fn (self $job) => $job->annotateTotals());
    }

    /**
     * Same data as completedDuringPeriod(), flattened to plain arrays with
     * PDF-ready photo thumbnails - see Metric::forDiaryPeriodExport's
     * identical reasoning.
     */
    public static function completedDuringPeriodExport(int $propertyId, $dateFrom, $dateTo)
    {
        return static::completedDuringPeriod($propertyId, $dateFrom, $dateTo)->map(fn (self $job) => [
            'id' => $job->id,
            'name' => $job->name,
            'description' => $job->description,
            'total_hours' => $job->total_hours,
            'total_expenses' => $job->total_expenses,
            'photos' => $job->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'pdf_thumbnail' => $photo->pdf_thumbnail,
            ]),
        ]);
    }

    /**
     * Every job on the property not currently on its "finished" status
     * (including one with no status at all), for the Diary report's Open
     * Jobs section - each annotated with total_hours/total_expenses booked
     * up to the end of the report period only (no lower bound - an open
     * job's running total as of that date, not scoped to the period start).
     */
    public static function openAsOf(int $propertyId, $dateTo)
    {
        return static::where('property_id', $propertyId)
            ->whereDoesntHave('jobStatus', fn ($query) => $query->where('is_finished_default', true))
            ->orderBy('name')
            ->get()
            ->each(fn (self $job) => $job->annotateTotals(upTo: $dateTo));
    }

    /**
     * Flattened counterpart to openAsOf() - see completedDuringPeriodExport().
     */
    public static function openAsOfExport(int $propertyId, $dateTo)
    {
        return static::openAsOf($propertyId, $dateTo)->map(fn (self $job) => [
            'id' => $job->id,
            'name' => $job->name,
            'total_hours' => $job->total_hours,
            'total_expenses' => $job->total_expenses,
        ]);
    }

    /**
     * Sets total_hours/total_expenses on this instance - finalised/approved
     * work session hours and amount-bearing expenses, optionally capped at
     * $upTo (used by openAsOf(); completedDuringPeriod() leaves it null for
     * a true all-time total). Same whereNotNull('amount') convention as
     * FarmJobController::index()'s total_expenses figure, excluding
     * needs_review expenses with no amount yet.
     */
    private function annotateTotals($upTo = null): void
    {
        $sessions = $this->workSessions()
            ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
            ->when($upTo, fn ($query) => $query->where('started_at', '<=', $upTo))
            ->get();

        $this->total_hours = round($sessions->sum('duration_in_hours'), 2);
        $this->total_expenses = round(
            $this->expenses()
                ->whereNotNull('amount')
                ->when($upTo, fn ($query) => $query->where('date', '<=', $upTo))
                ->sum('amount'),
            2
        );
    }
}
