<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetLocation extends Model
{
    protected $fillable = [
        'asset_id',
        'created_by',
        'latitude',
        'longitude',
        'shape',
        'line',
    ];

    protected $casts = [
        'shape' => 'array',
        'line' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
