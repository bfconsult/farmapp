<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShapeController extends Controller
{
    public function edit(Property $property)
    {
        $property->load(['shape', 'zones']);

        return Inertia::render('Properties/Shape', [
            'property' => $property,
            'shape' => $property->shape,
            'zones' => $property->zones,
        ]);
    }

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'coordinates' => 'required|array',
        ]);

        $property->shape()->updateOrCreate(
            ['property_id' => $property->id],
            ['coordinates' => $validated['coordinates']]
        );

        // Stays on the editor rather than returning to the property page,
        // since an admin may still want to save the non-working zone on the
        // same visit.
        return redirect()->route('shape.edit', $property)
            ->with('status', 'Boundary saved.');
    }

    public function destroy(Property $property)
    {
        $property->shape()->delete();

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Boundary removed.');
    }
}
