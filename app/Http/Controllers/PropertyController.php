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
            'canLeave' => $currentRole ? $this->canLeave($property, $currentRole) : false,
        ]);
    }

    /**
     * A member can leave freely unless doing so would strip the property of
     * its last admin - they're pointed at deleting the property instead.
     */
    private function canLeave(Property $property, string $roleType): bool
    {
        if ($roleType !== Role::ADMIN) {
            return true;
        }

        return $property->roles()->where('type', Role::ADMIN)->count() > 1;
    }

    public function leave(Property $property)
    {
        $user = Auth::user();
        $role = Role::where('user_id', $user->id)->where('property_id', $property->id)->firstOrFail();

        if (!$this->canLeave($property, $role->type)) {
            return back()->with('error', 'You are the last admin on this property - delete the property instead of leaving.');
        }

        $role->delete();

        if ((int) session('current_property_id') === $property->id) {
            session()->forget('current_property_id');
        }

        return redirect()->route('properties.index')->with('success', "You have left {$property->name}.");
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