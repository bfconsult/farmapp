<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A raw event from the LoRaWAN gateway, stored as received - see
 * LorawanIngestController.
 */
class LorawanEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'body' => 'array',
        'received_at' => 'datetime',
    ];
}
