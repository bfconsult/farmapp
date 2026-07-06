<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    public function recurringJob()
    {
        return $this->belongsTo(RecurringJob::class);
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
}
