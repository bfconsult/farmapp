<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    /*
     * Kinds: what the app treats an asset of the type as, whatever the
     * property calls the type. Each property has one type of each kind
     * (see typeFor), which can be renamed but not deleted.
     *
     * Two levels: categories (parent_id null - Fixed, Plant, Stock and the
     * property's own) and sub-types under one (parent_id). The kinds start
     * as sub-types of Fixed.
     */

    /** The category the kinds go under when they're made. */
    public const FIXED = 'Fixed';

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

    protected $fillable = ['property_id', 'parent_id', 'name', 'kind'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /** The category it's a sub-type of, or null for a category. */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /** The property's Fixed category - made again if it's gone. */
    public static function fixedCategoryFor(int $propertyId): self
    {
        return static::where('property_id', $propertyId)
            ->whereNull('parent_id')
            ->whereNull('kind')
            ->whereRaw('lower(trim(name)) in (?, ?)', ['fixed', 'fixed assets'])
            ->orderBy('id')
            ->first()
            ?? static::create(['property_id' => $propertyId, 'name' => self::FIXED]);
    }

    /**
     * The default asset type set for a brand-new property - mirrors
     * JobStatus::seedDefaultsForProperty(). Matches AssetTypeSeeder's
     * original values, plus one of each kind.
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        $fixed = static::create(['property_id' => $propertyId, 'name' => self::FIXED]);
        static::create(['property_id' => $propertyId, 'name' => 'Plant']);
        static::create(['property_id' => $propertyId, 'name' => 'Stock']);
        foreach (self::KINDS as $kind => $name) {
            static::create(['property_id' => $propertyId, 'parent_id' => $fixed->id, 'name' => $name, 'kind' => $kind]);
        }
    }

    /** The property's type of this kind - made again if it's been deleted. */
    public static function typeFor(int $propertyId, string $kind): self
    {
        return static::where('property_id', $propertyId)->where('kind', $kind)->first()
            ?? static::create([
                'property_id' => $propertyId,
                'parent_id' => self::fixedCategoryFor($propertyId)->id,
                'name' => self::KINDS[$kind],
                'kind' => $kind,
            ]);
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
