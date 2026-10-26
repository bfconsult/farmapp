<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One decoded field from one uplink, e.g. door_open = 1.
 */
class SensorReading extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'recorded_at' => 'datetime',
        'value' => 'float',
        'snr' => 'float',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
