<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    /**
     * No `index`/`edit` actions - zones are managed on the same page as the
     * boundary (Properties/Shape.jsx, via ShapeController@edit), not a
     * separate page.
     */
    public function store(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'coordinates' => 'required|array|min:3',
        ]);

        $property->zones()->create($validated);

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Zone added.');
    }

    public function update(Request $request, Property $property, Zone $zone)
    {
        abort_unless($zone->property_id === $property->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'coordinates' => 'required|array|min:3',
        ]);

        $zone->update($validated);

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Zone updated.');
    }

    public function destroy(Property $property, Zone $zone)
    {
        abort_unless($zone->property_id === $property->id, 404);

        $zone->delete();

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Zone removed.');
    }
}
