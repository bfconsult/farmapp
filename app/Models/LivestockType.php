<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LivestockType extends Model
{
    protected $fillable = ['property_id', 'name'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function livestock()
    {
        return $this->hasMany(Livestock::class);
    }

    /**
     * The default livestock types set for a brand-new property - mirrors
     * AssetType::seedDefaultsForProperty().
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create(['property_id' => $propertyId, 'name' => 'Cattle']);
        static::create(['property_id' => $propertyId, 'name' => 'Sheep']);
        static::create(['property_id' => $propertyId, 'name' => 'Goat']);
    }
}
