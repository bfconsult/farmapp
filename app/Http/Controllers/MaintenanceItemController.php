<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceItem;
use Illuminate\Http\Request;

class MaintenanceItemController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'repeat_period_days' => 'required|integer|min:1',
        ]);

        $asset->maintenanceItems()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'next_due_date' => $validated['start_date'],
        ]);

        return back()->with('success', 'Maintenance item added.');
    }

    public function update(Request $request, MaintenanceItem $maintenanceItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'repeat_period_days' => 'required|integer|min:1',
        ]);

        $maintenanceItem->update($validated);

        return back()->with('success', 'Maintenance item updated.');
    }

    public function destroy(MaintenanceItem $maintenanceItem)
    {
        $maintenanceItem->delete();

        return back()->with('success', 'Maintenance item deleted.');
    }

    public function convertToJob(Request $request, MaintenanceItem $maintenanceItem)
    {
        $job = $maintenanceItem->convertToJob($request->user());

        return redirect()->route('jobs.edit', $job);
    }
}
