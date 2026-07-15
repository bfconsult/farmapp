<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSessionWaypoint extends Model
{
    public $timestamps = false;

    protected $fillable = ['work_session_id', 'latitude', 'longitude', 'recorded_at'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function workSession()
    {
        return $this->belongsTo(WorkSession::class);
    }
}
