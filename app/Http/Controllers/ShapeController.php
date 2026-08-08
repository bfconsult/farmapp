<?php

namespace App\Http\Controllers;

use App\Models\Note;
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
            // Location notes (e.g. a fence corner) are shown as a toggleable
            // reference layer while drawing zones - not editable here, so
            // just the fields needed to label a pin.
            'notes' => Note::where('property_id', $property->id)
                ->whereNotNull('latitude')
                ->with('createdBy')
                ->get(),
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

        // Returns to whichever page submitted this - normally shape.edit
        // itself (an admin may still want to save the non-working zone on
        // the same visit, so staying there matters), but also reachable from
        // the quick boundary picker on Properties/Edit for a freshly created
        // property, which shouldn't get yanked onto the full editor.
        return back()->with('status', 'Boundary saved.');
    }

    public function destroy(Property $property)
    {
        $property->shape()->delete();

        return redirect()->route('shape.edit', $property)
            ->with('status', 'Boundary removed.');
    }
}
