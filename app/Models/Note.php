<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'property_id',
        'asset_id',
        'created_by',
        'body',
        'latitude',
        'longitude',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }
}
