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

    /**
     * Every job related to this asset: either directly (ad-hoc work not
     * tied to a maintenance schedule, via FarmJob.asset_id) or via one of
     * its maintenance items (FarmJob.maintenance_item_id). Not a true
     * Eloquent relation - no single FK/join path covers both - just a
     * query builder, but it supports the same read-only chaining
     * (with/count/pluck/latest) callers already use on a relation.
     */
    public function jobs()
    {
        return FarmJob::where('asset_id', $this->id)
            ->orWhereHas('maintenanceItem', fn ($query) => $query->where('asset_id', $this->id));
    }
}
