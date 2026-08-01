<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $fillable = ['property_id', 'name'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * The default asset type set for a brand-new property - mirrors
     * JobStatus::seedDefaultsForProperty(). Matches AssetTypeSeeder's
     * original values.
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create(['property_id' => $propertyId, 'name' => 'Fixed']);
        static::create(['property_id' => $propertyId, 'name' => 'Plant']);
        static::create(['property_id' => $propertyId, 'name' => 'Stock']);
    }
}
