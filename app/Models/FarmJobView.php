<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmJobView extends Model
{
    public $timestamps = false;

    protected $fillable = ['farm_job_id', 'user_id', 'viewed_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
