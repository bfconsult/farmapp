<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShapeController extends Controller
{
    public function edit(Property $property)
    {
        $property->load('shape');

        return Inertia::render('Properties/Shape', [
            'property' => $property,
            'shape' => $property->shape,
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

        return redirect()->route('properties.show', $property)
            ->with('status', 'Boundary saved.');
    }

    public function destroy(Property $property)
    {
        $property->shape()->delete();

        return redirect()->route('properties.show', $property)
            ->with('status', 'Boundary removed.');
    }
}
