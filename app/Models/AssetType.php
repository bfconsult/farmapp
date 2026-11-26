<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    /**
     * The kind of the type fences go in: assets of it are drawn as lines
     * and shown in the farm view. Each property has one (see
     * fenceTypeFor), whatever it chooses to call it.
     */
    public const FENCE = 'fence';

    protected $fillable = ['property_id', 'name', 'kind'];

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
        static::create(['property_id' => $propertyId, 'name' => 'Fence', 'kind' => self::FENCE]);
    }

    /** The property's fence type - made again if it's been deleted. */
    public static function fenceTypeFor(int $propertyId): self
    {
        return static::firstOrCreate(
            ['property_id' => $propertyId, 'kind' => self::FENCE],
            ['name' => 'Fence'],
        );
    }
}
