<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ground heights over a property on a regular lat/lng grid - see
 * App\Services\Geo\Terrain, which fetches them.
 */
class PropertyTerrain extends Model
{
    protected $fillable = ['property_id', 'north', 'south', 'west', 'east', 'columns', 'rows', 'heights', 'source'];

    protected $casts = [
        'north' => 'float',
        'south' => 'float',
        'west' => 'float',
        'east' => 'float',
        'columns' => 'integer',
        'rows' => 'integer',
        'heights' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Whether the grid still covers all of these [lat, lng] points - it
     * doesn't once the boundary or a paddock is drawn past its edge.
     */
    public function covers(array $points): bool
    {
        foreach ($points as [$lat, $lng]) {
            if ($lat > $this->north || $lat < $this->south || $lng < $this->west || $lng > $this->east) {
                return false;
            }
        }

        return true;
    }
}
