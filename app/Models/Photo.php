<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = ['job_id', 'work_session_id', 'file', 'time_taken', 'location'];

    protected $casts = [
        'time_taken' => 'datetime',
    ];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class, 'job_id');
    }

    public function workSession()
    {
        return $this->belongsTo(WorkSession::class);
    }
}