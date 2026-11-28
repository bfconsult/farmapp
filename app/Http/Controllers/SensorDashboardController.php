<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Role;
use App\Services\Lorawan\SensorTiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * The sensor dashboard: a tile per tank, gate and pump sensor, which each
 * person arranges (drags and resizes) for themselves - their layout is kept
 * on their role on the property. Everyone on the property can see it; only
 * managers can open a sensor's own page from it.
 */
class SensorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $propertyId = session('current_property_id');
        $property = $propertyId ? Property::find($propertyId) : null;
        $roleType = $property ? $request->user()->roleOn($property) : null;

        return Inertia::render('Manage/SensorDashboard', [
            'tiles' => (new SensorTiles($request->user()->displayTimezone()))->forProperty($propertyId),
            'layout' => $this->role($request)?->sensor_dashboard ?? [],
            'canManage' => in_array($roleType, ['admin', 'manager'], true),
        ]);
    }

    /** Saves this person's arrangement of the tiles. */
    public function updateLayout(Request $request)
    {
        $validated = $request->validate([
            'layout' => 'present|array|max:500',
            'layout.*.i' => 'required|string|max:50',
            'layout.*.x' => 'required|integer|min:0|max:12',
            'layout.*.y' => 'required|integer|min:0|max:10000',
            'layout.*.w' => 'required|integer|min:1|max:2',
            'layout.*.h' => 'required|integer|min:1|max:2',
        ]);

        $layout = collect($validated['layout'])
            ->map(fn ($item) => [
                'i' => $item['i'],
                'x' => (int) $item['x'],
                'y' => (int) $item['y'],
                // Snapped to a size a tile is designed for.
                ...$this->snap((int) $item['w'], (int) $item['h']),
            ])
            ->values()
            ->all();

        $this->role($request)?->update(['sensor_dashboard' => $layout]);

        return back();
    }

    /** The nearest tile size: one column wide is always small. */
    private function snap(int $w, int $h): array
    {
        return $w === 1 ? ['w' => 1, 'h' => 1] : ['w' => 2, 'h' => $h];
    }

    private function role(Request $request): ?Role
    {
        return Role::where('user_id', $request->user()->id)
            ->where('property_id', session('current_property_id'))
            ->first();
    }
}
