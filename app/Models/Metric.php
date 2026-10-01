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
     * Metrics for a property with latestMeasurement replaced by whichever
     * measurement's period overlaps the given range - used by Diary views
     * (Reports/Diary, Diary/SharedView) so they show the value that was
     * current *during* that range, not whatever is current *now* (mirrors
     * WorkSession::diaryDays' pattern of building exactly what these Diary
     * pages need in one place).
     */
    public static function forDiaryPeriod(int $propertyId, Carbon $dateFrom, Carbon $dateTo)
    {
        return static::where('property_id', $propertyId)
            ->with(['measurements' => function ($query) use ($dateFrom, $dateTo) {
                $query->where('period_start', '<=', $dateTo)
                    ->where('period_end', '>=', $dateFrom)
                    ->orderByDesc('period_start')
                    ->with('photos');
            }])
            ->orderBy('name')
            ->get()
            ->each(function (self $metric) {
                $metric->setRelation('latestMeasurement', $metric->measurements->first());
                $metric->unsetRelation('measurements');
            });
    }

    /**
     * Same data as forDiaryPeriod(), flattened to plain arrays - a Blade PDF
     * view works with the raw objects it's handed, so it can't rely on
     * Inertia's camelCase-to-snake_case relation conversion the way
     * latest_measurement is accessed in the frontend Diary views.
     */
    public static function forDiaryPeriodExport(int $propertyId, Carbon $dateFrom, Carbon $dateTo)
    {
        return static::forDiaryPeriod($propertyId, $dateFrom, $dateTo)->map(fn (self $metric) => [
            'name' => $metric->name,
            'measurement' => $metric->latestMeasurement ? [
                'status' => $metric->latestMeasurement->status,
                'answer_type' => $metric->latestMeasurement->answer_type,
                'value_number' => $metric->latestMeasurement->value_number,
                'value_text' => $metric->latestMeasurement->value_text,
            ] : null,
        ]);
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
     * The period_start a brand-new measurement should use to cover the given
     * date - calendar-aligned for monthly/quarterly/yearly/daily, so this is
     * safe to use for an arbitrary past date (e.g. backfilling a missed
     * month), unlike the day-after-the-last-period chaining the scheduler
     * uses for its own next-period rows. Weekly snaps to the calendar week
     * (Mon-Sun) for the same reason - a reasonable default for an ad hoc
     * backfill, even though it may not align with this metric's existing
     * week-to-week chain if one exists.
     */
    public function periodStartFor(Carbon $date): Carbon
    {
        return match ($this->reporting_period) {
            self::DAILY => $date->copy(),
            self::WEEKLY => $date->copy()->startOfWeek(),
            self::MONTHLY => $date->copy()->startOfMonth(),
            self::QUARTERLY => $date->copy()->startOfQuarter(),
            self::YEARLY => $date->copy()->startOfYear(),
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
