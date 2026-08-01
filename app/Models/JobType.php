<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobType extends Model
{
    protected $fillable = ['property_id', 'name', 'color'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }

    /**
     * The default job type set for a brand-new property - mirrors
     * JobStatus::seedDefaultsForProperty(). Matches JobTypeSeeder's original
     * values (no colors set there either).
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create(['property_id' => $propertyId, 'name' => 'Maintenance']);
        static::create(['property_id' => $propertyId, 'name' => 'Improvement']);
        static::create(['property_id' => $propertyId, 'name' => 'Proposed']);
    }
}
