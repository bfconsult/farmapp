<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricMeasurement extends Model
{
    const INCOMPLETE = 'incomplete';
    const COMPLETE = 'complete';

    const STATUSES = [self::INCOMPLETE, self::COMPLETE];

    protected $fillable = [
        'metric_id',
        'name',
        'answer_type',
        'period_start',
        'period_end',
        'status',
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
