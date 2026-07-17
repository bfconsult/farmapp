<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Metric extends Model
{
    const DAILY = 'daily';
    const WEEKLY = 'weekly';
    const MONTHLY = 'monthly';
    const QUARTERLY = 'quarterly';
    const YEARLY = 'yearly';

    const REPORTING_PERIODS = [self::DAILY, self::WEEKLY, self::MONTHLY, self::QUARTERLY, self::YEARLY];

    const NUMBER = 'number';
    const TEXT = 'text';

    const ANSWER_TYPES = [self::NUMBER, self::TEXT];

    protected $fillable = [
        'property_id',
        'created_by',
        'name',
        'description',
        'reporting_period',
        'answer_type',
        'is_active',
    ];

    protected $casts = [
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

    public function measurements()
    {
        return $this->hasMany(MetricMeasurement::class);
    }

    public function latestMeasurement()
    {
        return $this->hasOne(MetricMeasurement::class)->latestOfMany('period_start');
    }

    /**
     * The end date of a period starting on the given date, per this metric's
     * reporting period - monthly/quarterly/yearly snap to calendar
     * boundaries, daily/weekly are fixed-length windows from $periodStart
     * (mirrors RecurringJob::periodEndFor()).
     */
    public function periodEndFor(Carbon $periodStart): Carbon
    {
        return match ($this->reporting_period) {
            self::DAILY => $periodStart->copy(),
            self::WEEKLY => $periodStart->copy()->addDays(6),
            self::MONTHLY => $periodStart->copy()->endOfMonth(),
            self::QUARTERLY => $periodStart->copy()->endOfQuarter(),
            self::YEARLY => $periodStart->copy()->endOfYear(),
        };
    }

    /**
     * Create this metric's measurement for the period starting on the given
     * date - used both by the daily scheduler and to open the first
     * measurement immediately when a metric is created. name/answer_type are
     * snapshotted onto the measurement so it stays meaningful even if this
     * metric is later renamed or deleted.
     */
    public function createMeasurement(Carbon $periodStart): MetricMeasurement
    {
        return $this->measurements()->create([
            'name' => $this->name,
            'answer_type' => $this->answer_type,
            'period_start' => $periodStart,
            'period_end' => $this->periodEndFor($periodStart),
            'status' => MetricMeasurement::INCOMPLETE,
        ]);
    }
}
