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
                ];
            })
            ->values();

        return response()->json(['properties' => $properties]);
    }
}
