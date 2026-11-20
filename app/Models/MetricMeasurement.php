<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricMeasurement extends Model
{
    const INCOMPLETE = 'incomplete';
    const COMPLETE = 'complete';

    const STATUSES = [self::INCOMPLETE, self::COMPLETE];

    // Where a linked metric's value came from (see the source column).
    const SENSOR = 'sensor';
    const MANUAL = 'manual';

    protected $fillable = [
        'metric_id',
        'name',
        'answer_type',
        'period_start',
        'period_end',
        'status',
        'source',
        'value_number',
        'value_text',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'value_number' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function metric()
    {
        return $this->belongsTo(Metric::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }
}
