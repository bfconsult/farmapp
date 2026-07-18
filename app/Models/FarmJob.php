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
        'zone_id',
        'recurring_job_id',
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

    public function zone()
    {
        return $this->belongsTo(Zone::class);
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

    public function checklists()
    {
        return $this->hasMany(Checklist::class);
    }

    public function incompleteChecklists()
    {
        return $this->hasMany(Checklist::class)->where('status', Checklist::INCOMPLETE);
    }
}
