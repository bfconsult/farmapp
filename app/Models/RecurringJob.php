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
}
