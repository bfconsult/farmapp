<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A raw event from the LoRaWAN gateway, stored as received - see
 * LorawanIngestController. Uplinks from a registered device are decoded
 * into SensorReadings (processed_at set); the rest wait, so a device
 * registered later can have its backlog decoded.
 */
class LorawanEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'body' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'dev_eui', 'dev_eui');
    }
}
