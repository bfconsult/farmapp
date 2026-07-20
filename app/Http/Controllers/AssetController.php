<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FarmJob;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Asset::create([
            ...$validated,
            'property_id' => session('current_property_id'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Asset created.');
    }

    public function update(Request $request, Asset $asset)
    {
        $asset->update($this->validated($request));

        return back()->with('success', 'Asset updated.');
    }

    /**
     * An asset has at most one of a point location or a shape at a time -
     * saving one clears the other, mirroring how Properties/Shape.jsx saves
     * a zone's polygon immediately on draw, no separate Save step.
     */
    public function updateLocation(Request $request, Asset $asset)
    {
        if ($request->has('shape')) {
            $validated = $request->validate([
                'shape' => 'required|array|min:3',
            ]);

            $asset->update([
                'shape' => $validated['shape'],
                'latitude' => null,
                'longitude' => null,
            ]);
        } elseif ($request->has('latitude')) {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $asset->update([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'shape' => null,
            ]);
        } else {
            $asset->update(['latitude' => null, 'longitude' => null, 'shape' => null]);
        }

        return back()->with('success', 'Location updated.');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return back()->with('success', 'Asset deleted.');
    }

    public function show(Asset $asset)
    {
        $asset->load(['assetType', 'maintenanceItems', 'property.shape']);

        $jobIds = $asset->jobs()->pluck('farm_jobs.id');

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'recentJobs' => FarmJob::whereIn('id', $jobIds)->with('jobStatus')->latest()->take(5)->get(),
            'jobsCount' => $jobIds->count(),
            'bookedHours' => round(
                WorkSession::whereIn('farm_job_id', $jobIds)
                    ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
                    ->get()
                    ->sum('duration_in_hours'),
                2
            ),
        ]);
    }

    public function jobHistory(Asset $asset)
    {
        return Inertia::render('Assets/JobHistory', [
            'asset' => $asset,
            'jobs' => $asset->jobs()->with('jobStatus')->latest('farm_jobs.created_at')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'value' => 'nullable|numeric|min:0',
        ]);
    }
}
