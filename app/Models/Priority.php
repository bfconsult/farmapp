<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    protected $fillable = ['property_id', 'name', 'color', 'order'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }

    /**
     * The default priority set for a brand-new property - mirrors
     * JobStatus::seedDefaultsForProperty(). Matches PrioritySeeder's
     * original values (no colors set there either).
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create(['property_id' => $propertyId, 'name' => 'Low', 'order' => 1]);
        static::create(['property_id' => $propertyId, 'name' => 'Medium', 'order' => 2]);
        static::create(['property_id' => $propertyId, 'name' => 'High', 'order' => 3]);
        static::create(['property_id' => $propertyId, 'name' => 'Critical', 'order' => 4]);
    }
}
