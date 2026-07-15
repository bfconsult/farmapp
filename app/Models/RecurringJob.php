<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringJob extends Model
{
    const DAILY = 'daily';
    const WEEKLY = 'weekly';
    const MONTHLY = 'monthly';
    const YEARLY = 'yearly';

    const INTERVALS = [self::DAILY, self::WEEKLY, self::MONTHLY, self::YEARLY];

    protected $fillable = [
        'property_id',
        'zone_id',
        'created_by',
        'name',
        'description',
        'job_type_id',
        'priority_id',
        'estimated_hours',
        'budget',
        'hourly_rate',
        'interval',
        'starts_on',
        'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobType()
    {
        return $this->belongsTo(JobType::class);
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class);
    }

    public function instances()
    {
        return $this->hasMany(FarmJob::class);
    }

    /**
     * The end date of a period starting on the given date, per this
     * template's interval — monthly/yearly snap to calendar boundaries.
     */
    public function periodEndFor(\Carbon\Carbon $periodStart): \Carbon\Carbon
    {
        return match ($this->interval) {
            self::DAILY => $periodStart->copy(),
            self::WEEKLY => $periodStart->copy()->addDays(6),
            self::MONTHLY => $periodStart->copy()->endOfMonth(),
            self::YEARLY => $periodStart->copy()->endOfYear(),
        };
    }

    /**
     * Create this template's job instance for the period starting on the
     * given date — used both by the daily scheduler and to create the first
     * instance immediately when a recurring job is created.
     */
    public function createInstance(\Carbon\Carbon $periodStart): FarmJob
    {
        $job = FarmJob::create([
            'name' => $this->name,
            'description' => $this->description,
            'estimated_hours' => $this->estimated_hours,
            'budget' => $this->budget,
            'hourly_rate' => $this->hourly_rate,
            'priority_id' => $this->priority_id,
            'job_type_id' => $this->job_type_id,
            'job_status_id' => JobStatus::where('is_default', true)->value('id'),
            'user_id' => $this->created_by,
            'property_id' => $this->property_id,
            'zone_id' => $this->zone_id,
            'recurring_job_id' => $this->id,
            'period_start' => $periodStart,
            'period_end' => $this->periodEndFor($periodStart),
        ]);

        $teamUserIds = Role::where('property_id', $job->property_id)->pluck('user_id');
        $job->assignees()->attach($teamUserIds);

        return $job;
    }
}
