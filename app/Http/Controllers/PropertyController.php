<?php

namespace App\Http\Controllers;

use App\Models\AssetType;
use App\Models\JobStatus;
use App\Models\JobType;
use App\Models\Priority;
use App\Models\Property;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PropertyController extends Controller
{
    /**
     * Creates a property with a placeholder name and no other details, gives
     * the creator an admin role on it, and switches the session to it -
     * there's no form here, so the real name/address get filled in on the
     * Edit page this redirects to instead.
     */
    public function store()
    {
        $property = Property::create(['name' => 'New Property', 'address' => '']);

        Auth::user()->roles()->create([
            'property_id' => $property->id,
            'type' => Role::ADMIN,
        ]);

        JobStatus::seedDefaultsForProperty($property->id);
        Priority::seedDefaultsForProperty($property->id);
        JobType::seedDefaultsForProperty($property->id);
        AssetType::seedDefaultsForProperty($property->id);

        session(['current_property_id' => $property->id]);
        Auth::user()->update(['current_property_id' => $property->id]);

        return redirect()->route('properties.edit', $property);
    }

    public function show(Property $property)
    {
        abort_unless(Auth::user()->roleOn($property), 403);

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
        if ($user->current_property_id === $property->id) {
            $user->update(['current_property_id' => null]);
        }

        return redirect()->route('profile.edit')->with('success', "You have left {$property->name}.");
    }

    public function edit(Property $property)
    {
        abort_unless(Auth::user()->roleOn($property) === Role::ADMIN, 403);

        return Inertia::render('Properties/Edit', [
            'property' => $property,
        ]);
    }

    public function update(Request $request, Property $property)
    {
        abort_unless(Auth::user()->roleOn($property) === Role::ADMIN, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $property->update($validated);

        return redirect()->route('properties.show', $property);
    }

    public function destroy(Property $property)
    {
        abort_unless(Auth::user()->roleOn($property) === Role::ADMIN, 403);

        if ((int) session('current_property_id') === $property->id) {
            session()->forget('current_property_id');
        }

        $property->delete();

        return redirect()->route('profile.edit');
    }
}