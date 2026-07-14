<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class NonWorkingZoneController extends Controller
{
    /**
     * No `edit` action - the non-working zone is edited on the same page as
     * the boundary (Properties/Shape.jsx, via ShapeController@edit), not a
     * separate page.
     */
    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'center_lat' => 'required|numeric|between:-90,90',
            'center_lng' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|numeric|min:1',
        ]);

        $property->update([
            'non_working_zone_center_lat' => $validated['center_lat'],
            'non_working_zone_center_lng' => $validated['center_lng'],
            'non_working_zone_radius_meters' => $validated['radius_meters'],
        ]);

        // Stays on the boundary/zone editor rather than returning to the
        // property page, since an admin may still want to save the other
        // shape (boundary or zone) on the same visit.
        return redirect()->route('shape.edit', $property)
            ->with('status', 'Non-working zone saved.');
    }

    public function destroy(Property $property)
    {
        $property->update([
            'non_working_zone_center_lat' => null,
            'non_working_zone_center_lng' => null,
            'non_working_zone_radius_meters' => null,
        ]);

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Non-working zone removed.');
    }
}
