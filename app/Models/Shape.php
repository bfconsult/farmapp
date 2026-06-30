<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shape extends Model
{
    protected $fillable = ['property_id', 'coordinates'];

    protected $casts = [
        'coordinates' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}