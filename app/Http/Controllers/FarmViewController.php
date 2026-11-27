<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Device;
use App\Models\Livestock;
use App\Models\Mob;
use App\Models\Property;
use App\Models\PropertyTerrain;
use App\Services\Geo\Terrain;
use App\Services\Lorawan\SensorKinds;
use App\Services\Lorawan\TankActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * The cartoon "farm view": the property drawn as an angled 3D scene, with
 * fenced paddocks, its mobs grazing in them, its trees, and its gates,
 * tanks and pumps showing their sensors' state. A prototype, for app admins only for now.
 */
class FarmViewController extends Controller
{
    public function index()
    {
        $propertyId = session('current_property_id');
        $property = Property::with(['shape', 'zones'])->find($propertyId);
        $terrain = PropertyTerrain::where('property_id', $propertyId)->first();

        return Inertia::render('FarmView', [
            'boundary' => $property?->shape?->coordinates,
            'zones' => $property?->zones->map(fn ($zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'coordinates' => $zone->coordinates,
            ])->values() ?? [],
            'mobs' => $this->mobs($propertyId),
            'props' => $this->props($propertyId),
            'fences' => $this->fences($propertyId),
            'vegetation' => $this->vegetation($propertyId),
            'rotation' => (int) ($property?->farm_view_rotation ?? 0),
            'terrain' => $terrain ? [
                'north' => $terrain->north,
                'south' => $terrain->south,
                'west' => $terrain->west,
                'east' => $terrain->east,
                'columns' => $terrain->columns,
                'rows' => $terrain->rows,
                'heights' => $terrain->heights,
            ] : null,
            // Drawn past the edge of the heights fetched, so fetch again.
            'terrainStale' => $terrain && $property && ! $terrain->covers($this->landPoints($property)),
        ]);
    }

    /**
     * Fetch the ground heights under the property, for its hills and contours.
     */
    public function fetchTerrain(Terrain $terrain)
    {
        $property = Property::with(['shape', 'zones'])->findOrFail(session('current_property_id'));

        try {
            $terrain->fetchFor($property->id, $this->landPoints($property));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /** The boundary's and paddocks' [lat, lng] points. */
    private function landPoints(Property $property): array
    {
        return array_values(array_merge(
            $property->shape?->coordinates ?? [],
            ...$property->zones->map(fn ($zone) => $zone->coordinates ?? [])->all(),
        ));
    }

    /**
     * Save which way round the property is shown - its "right way up".
     */
    public function updateRotation(Request $request)
    {
        $validated = $request->validate(['rotation' => 'required|integer|min:0|max:359']);

        Property::whereKey(session('current_property_id'))->update(['farm_view_rotation' => $validated['rotation']]);

        return back();
    }

    /**
     * Mobs in a paddock: {id, name, zone_id, count, type} - the count is
     * its animals still on the property, the type their most common one.
     */
    private function mobs(?int $propertyId): array
    {
        return Mob::where('property_id', $propertyId)
            ->with('currentZone')
            ->orderBy('name')
            ->get()
            ->filter(fn (Mob $mob) => $mob->currentZone?->zone_id)
            ->map(function (Mob $mob) {
                $animals = Livestock::where('livestock.mob_id', $mob->id)
                    ->where(fn ($query) => $query->whereNull('livestock.status')->orWhere('livestock.status', 'active'))
                    ->leftJoin('livestock_types', 'livestock_types.id', '=', 'livestock.livestock_type_id')
                    ->selectRaw('livestock_types.name as type, count(*) as count')
                    ->groupBy('livestock_types.name')
                    ->orderByDesc('count')
                    ->get();

                return [
                    'id' => $mob->id,
                    'name' => $mob->name,
                    'zone_id' => $mob->currentZone->zone_id,
                    'count' => (int) $animals->sum('count'),
                    'type' => $animals->first()?->type,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Fence assets drawn as lines: {id, name, fence_type, wires, line}.
     */
    private function fences(?int $propertyId): array
    {
        return Asset::where('property_id', $propertyId)
            ->fences()
            ->with('currentLocation')
            ->orderBy('name')
            ->get()
            ->filter(fn (Asset $asset) => count($asset->currentLocation?->line ?? []) > 1)
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'fence_type' => $asset->details['fence_type'] ?? null,
                'wires' => $asset->details['wires'] ?? null,
                'line' => $asset->currentLocation->line,
            ])
            ->values()
            ->all();
    }

    /**
     * Gates, tanks, pumps and troughs: {key, kind, name, latitude,
     * longitude, state, percent (a tank's or trough's level), monitored,
     * trough (its size), url}. Each such asset with a location, showing the
     * state of a sensor of its kind (a trough's: a level sensor)
     * fitted to it (if any); then placed sensors with a kind that don't
     * stand for one of those, where they are.
     */
    private function props(?int $propertyId): array
    {
        $assets = Asset::where('property_id', $propertyId)
            ->ofKind(...array_keys(AssetType::SENSOR_KINDS))
            ->with(['assetType', 'currentLocation', 'devices'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Asset $asset) => $asset->point() !== null);

        $shown = [];
        $props = $assets->map(function (Asset $asset) use (&$shown) {
            $device = $asset->devices->firstWhere('kind', AssetType::SENSOR_KINDS[$asset->kind()]);
            if ($device) {
                $shown[] = $device->id;
            }
            [$latitude, $longitude] = $asset->point();

            return [
                'key' => 'asset-'.$asset->id,
                'kind' => $asset->kind(),
                'name' => $asset->name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                ...$this->sensorState($device),
                // A trough's shape and size, to draw it.
                'trough' => $asset->kind() === AssetType::TROUGH
                    ? Asset::TROUGH_SIZES[$asset->details['trough_size'] ?? 'other'] ?? Asset::TROUGH_SIZES['other']
                    : null,
                'url' => route('assets.show', $asset),
            ];
        });

        $sensors = Device::where('property_id', $propertyId)
            ->whereIn('kind', ['gate', 'tank', 'pump'])
            ->whereNotIn('id', $shown)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [
                'key' => 'sensor-'.$device->id,
                'kind' => $device->kind,
                'name' => $device->name,
                'latitude' => (float) $device->latitude,
                'longitude' => (float) $device->longitude,
                ...$this->sensorState($device),
                'trough' => null,
                'url' => route('manage.sensors.show', $device),
            ]);

        return $props->concat($sensors)->values()->all();
    }

    /** What a prop's sensor says: {state, percent, monitored}. */
    private function sensorState(?Device $device): array
    {
        if (! $device) {
            return ['state' => null, 'percent' => null, 'monitored' => false];
        }

        $percent = null;
        if ($device->kind === 'tank') {
            $reading = $device->readings()->where('metric', 'distance')->latest('recorded_at')->latest('id')->first();
            $percent = $reading ? TankActivity::level($device, $reading->value)['percent'] ?? null : null;
        }

        return ['state' => SensorKinds::state($device), 'percent' => $percent, 'monitored' => true];
    }

    /**
     * Vegetation assets with a location: {id, name, vegetation_type, point
     * ([lat, lng]: one tree or clump), shape (an area of it) or line (a row)}.
     */
    private function vegetation(?int $propertyId): array
    {
        return Asset::where('property_id', $propertyId)
            ->ofKind(AssetType::VEGETATION)
            ->with('currentLocation')
            ->orderBy('name')
            ->get()
            ->map(function (Asset $asset) {
                $location = $asset->currentLocation;
                $point = $location && $location->latitude !== null && $location->longitude !== null
                    ? [(float) $location->latitude, (float) $location->longitude]
                    : null;

                return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'vegetation_type' => $asset->details['vegetation_type'] ?? 'tree',
                    'point' => $point,
                    'shape' => count($location?->shape ?? []) > 2 ? $location->shape : null,
                    'line' => count($location?->line ?? []) > 1 ? $location->line : null,
                ];
            })
            ->filter(fn ($vegetation) => $vegetation['point'] || $vegetation['shape'] || $vegetation['line'])
            ->values()
            ->all();
    }
}
