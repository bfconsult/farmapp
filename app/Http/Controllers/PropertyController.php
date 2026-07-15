<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PropertyController extends Controller
{
    public function index()
    {
        $properties = Auth::user()->properties()->with('shape')->get();

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
        ]);
    }

    public function create()
    {
        return Inertia::render('Properties/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $property = Property::create($validated);

        Auth::user()->roles()->create([
            'property_id' => $property->id,
            'type' => Role::ADMIN,
        ]);

        return redirect()->route('properties.index');
    }

    public function show(Property $property)
    {
        $property->load('shape');

        $currentRole = Auth::user()->roleOn($property);

        return Inertia::render('Properties/Show', [
            'property' => $property,
            'currentRole' => $currentRole,
        ]);
    }

    public function edit(Property $property)
    {
        return Inertia::render('Properties/Edit', [
            'property' => $property,
        ]);
    }

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $property->update($validated);

        return redirect()->route('properties.show', $property);
    }

    public function destroy(Property $property)
    {
        $property->delete();

        return redirect()->route('properties.index');
    }
}