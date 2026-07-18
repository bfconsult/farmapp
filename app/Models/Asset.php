<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'property_id',
        'asset_type_id',
        'created_by',
        'name',
        'description',
        'value',
        'latitude',
        'longitude',
        'shape',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'shape' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenanceItems()
    {
        return $this->hasMany(MaintenanceItem::class)->orderBy('next_due_date');
    }
}
