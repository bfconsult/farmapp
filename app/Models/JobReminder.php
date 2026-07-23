<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobReminder extends Model
{
    const TYPE_OVERDUE_NO_HOURS = 'overdue_no_hours';

    protected $fillable = ['farm_job_id', 'type', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }
}
