<?php

namespace App\Http\Controllers;

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
 * fenced paddocks, its mobs grazing in them and its sensors showing their
 * state. A prototype, for app admins only for now.
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
            'sensors' => $this->sensors($propertyId),
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
     * Placed sensors with a kind: {id, name, kind, latitude, longitude,
     * state, percent (a tank's level)}.
     */
    private function sensors(?int $propertyId): array
    {
        return Device::where('property_id', $propertyId)
            ->whereNotNull('kind')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) {
                $percent = null;
                if ($device->kind === 'tank') {
                    $reading = $device->readings()->where('metric', 'distance')->latest('recorded_at')->latest('id')->first();
                    $percent = $reading ? TankActivity::level($device, $reading->value)['percent'] ?? null : null;
                }

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'kind' => $device->kind,
                    'latitude' => (float) $device->latitude,
                    'longitude' => (float) $device->longitude,
                    'state' => SensorKinds::state($device),
                    'percent' => $percent,
                ];
            })
            ->all();
    }
}
