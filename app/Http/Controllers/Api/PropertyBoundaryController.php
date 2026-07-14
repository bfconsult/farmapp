<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Geo\BoundingCircleCalculator;
use Illuminate\Http\Request;

class PropertyBoundaryController extends Controller
{
    public function index(Request $request)
    {
        $properties = $request->user()->properties()
            ->with('shape')
            ->get()
            ->filter(fn (Property $property) => $property->shape !== null)
            ->map(function (Property $property) {
                $circle = BoundingCircleCalculator::forPolygon($property->shape->coordinates);

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'coordinates' => $property->shape->coordinates,
                    'geofence' => [
                        'center_lat' => $circle['lat'],
                        'center_lng' => $circle['lng'],
                        'radius_meters' => $circle['radius_meters'],
                    ],
                    // The on-site residence, if configured - unlike `geofence`
                    // above, not computed from the polygon; persisted exactly
                    // as an admin drew it (NonWorkingZoneController).
                    'non_working_zone' => $property->non_working_zone_center_lat !== null
                        ? [
                            'center_lat' => (float) $property->non_working_zone_center_lat,
                            'center_lng' => (float) $property->non_working_zone_center_lng,
                            'radius_meters' => (float) $property->non_working_zone_radius_meters,
                        ]
                        : null,
                ];
            })
            ->values();

        return response()->json(['properties' => $properties]);
    }
}
