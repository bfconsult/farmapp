<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = ['property_id', 'name', 'coordinates'];

    protected $casts = [
        'coordinates' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function workSessions()
    {
        return $this->hasMany(WorkSession::class);
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
