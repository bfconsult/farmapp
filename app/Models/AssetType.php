<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    /*
     * Kinds: what the app treats an asset of the type as, whatever the
     * property calls the type. Each property has one type of each kind
     * (see typeFor), which can be renamed but not deleted.
     */

    /** Drawn as a line, and as a fence in the farm view. */
    public const FENCE = 'fence';

    /** A gate, tank or pump: shown in the farm view, with the state of a sensor fitted to it. */
    public const GATE = 'gate';

    public const TANK = 'tank';

    public const PUMP = 'pump';

    /** A stock trough: shown in the farm view, its size picked from standard ones. */
    public const TROUGH = 'trough';

    /** Trees, shrubs or undergrowth, in the farm view. */
    public const VEGETATION = 'vegetation';

    /** Each kind with the name of a new type of it. */
    public const KINDS = [
        self::FENCE => 'Fence',
        self::GATE => 'Gate',
        self::TANK => 'Water tank',
        self::PUMP => 'Pump',
        self::TROUGH => 'Trough',
        self::VEGETATION => 'Vegetation',
    ];

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
     * original values, plus one of each kind.
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create(['property_id' => $propertyId, 'name' => 'Fixed']);
        static::create(['property_id' => $propertyId, 'name' => 'Plant']);
        static::create(['property_id' => $propertyId, 'name' => 'Stock']);
        foreach (self::KINDS as $kind => $name) {
            static::create(['property_id' => $propertyId, 'name' => $name, 'kind' => $kind]);
        }
    }

    /** The property's type of this kind - made again if it's been deleted. */
    public static function typeFor(int $propertyId, string $kind): self
    {
        return static::firstOrCreate(
            ['property_id' => $propertyId, 'kind' => $kind],
            ['name' => self::KINDS[$kind]],
        );
    }

    /**
     * The kind of sensor whose readings an asset of this kind shows: a
     * tank's or trough's level sensor, a gate's or pump's own.
     */
    public const SENSOR_KINDS = [
        self::GATE => 'gate',
        self::TANK => 'tank',
        self::PUMP => 'pump',
        self::TROUGH => 'tank',
    ];

    /** Kinds that hold water, with a capacity a level sensor works out litres from. */
    public const HOLDS_WATER = [self::TANK, self::TROUGH];

    /** The property's fence type. */
    public static function fenceTypeFor(int $propertyId): self
    {
        return self::typeFor($propertyId, self::FENCE);
    }
}
