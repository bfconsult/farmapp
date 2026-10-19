<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceItem;
use Illuminate\Http\Request;

class MaintenanceItemController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $validated = $this->validated($request);

        $asset->maintenanceItems()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'next_due_date' => $validated['repeat_period_days'] ? $validated['start_date'] : null,
            // Hours of use count from now - earlier use doesn't make a
            // brand-new item instantly due.
            'usage_baseline_hours' => $validated['repeat_usage_hours'] !== null ? $asset->usageHours() : null,
            'auto_generate' => $request->boolean('auto_generate'),
        ]);

        return back()->with('success', 'Maintenance item added.');
    }

    public function update(Request $request, MaintenanceItem $maintenanceItem)
    {
        $validated = $this->validated($request);

        $maintenanceItem->update([
            ...$validated,
            // A trigger being added to an existing item starts from now;
            // one that's kept keeps its due point.
            'next_due_date' => $validated['repeat_period_days']
                ? ($maintenanceItem->next_due_date ?? $validated['start_date'])
                : null,
            'usage_baseline_hours' => $validated['repeat_usage_hours'] !== null
                ? ($maintenanceItem->usage_baseline_hours ?? $maintenanceItem->asset->usageHours())
                : null,
            'auto_generate' => $request->boolean('auto_generate'),
        ]);

        return back()->with('success', 'Maintenance item updated.');
    }

    /**
     * Due by date, by hours of use, or both (whichever comes first) - at
     * least one is required. The start date only matters for the date one.
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'repeat_period_days' => 'nullable|integer|min:1|required_without:repeat_usage_hours',
            'start_date' => 'nullable|date|required_with:repeat_period_days',
            'repeat_usage_hours' => 'nullable|numeric|min:0.25|max:100000',
        ], [
            'repeat_period_days.required_without' => 'Set a number of days, a number of hours of use, or both.',
        ]);

        return [
            ...$validated,
            'repeat_period_days' => $validated['repeat_period_days'] ?? null,
            'start_date' => ($validated['repeat_period_days'] ?? null) ? $validated['start_date'] : null,
            'repeat_usage_hours' => $validated['repeat_usage_hours'] ?? null,
        ];
    }

    public function destroy(MaintenanceItem $maintenanceItem)
    {
        $maintenanceItem->delete();

        return back()->with('success', 'Maintenance item deleted.');
    }

    public function convertToJob(Request $request, MaintenanceItem $maintenanceItem)
    {
        // The next one only starts once the open job is completed - send
        // them to that instead of creating a duplicate.
        if ($openJob = $maintenanceItem->openJob()) {
            return redirect()->route('jobs.show', $openJob)
                ->with('error', 'This maintenance item already has an open job.');
        }

        $job = $maintenanceItem->convertToJob($request->user());

        return redirect()->route('jobs.edit', $job);
    }
}
