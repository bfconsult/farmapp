<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $fillable = ['job_id', 'work_session_id', 'metric_measurement_id', 'checklist_item_id', 'note_id', 'expense_id', 'file', 'time_taken', 'location'];

    protected $casts = [
        'time_taken' => 'datetime',
    ];

    protected $appends = ['url'];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class, 'job_id');
    }

    public function workSession()
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function metricMeasurement()
    {
        return $this->belongsTo(MetricMeasurement::class);
    }

    public function checklistItem()
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function getUrlAttribute()
    {
        $disk = Storage::disk(config('filesystems.default'));

        // S3 buckets aren't necessarily public-readable, so use a signed URL
        // rather than assuming a public ACL/bucket policy is in place.
        return config('filesystems.default') === 's3'
            ? $disk->temporaryUrl($this->file, now()->addHour())
            : $disk->url($this->file);
    }
}