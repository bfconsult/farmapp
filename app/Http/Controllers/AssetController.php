<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AssetController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');
        $currentProperty = $currentPropertyId ? Property::find($currentPropertyId) : null;
        $canManage = in_array(Auth::user()->roleOn($currentProperty), ['admin', 'manager'], true);

        // Visible to every role - only the CRUD/location controls on top of
        // this are canManage-gated.
        $assets = Asset::where('property_id', $currentPropertyId)
            ->with(['assetType', 'maintenanceItems'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Manage/Assets', [
            'assets' => $assets,
            'assetTypes' => $canManage ? AssetType::where('property_id', $currentPropertyId)->orderBy('name')->get() : [],
            'canManage' => $canManage,
        ]);
    }

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
     * Records a new current location for this asset - every call inserts a
     * fresh AssetLocation row (including "clear", which inserts an all-null
     * row) rather than overwriting one, so location history accumulates. An
     * asset has at most one of a point location or a shape at a time within
     * a single row - saving one clears the other, mirroring how
     * Properties/Shape.jsx saves a zone's polygon immediately on draw, no
     * separate Save step.
     */
    public function updateLocation(Request $request, Asset $asset)
    {
        if ($request->has('shape')) {
            $validated = $request->validate([
                'shape' => 'required|array|min:3',
            ]);

            $asset->locations()->create([
                'shape' => $validated['shape'],
                'created_by' => $request->user()->id,
            ]);
        } elseif ($request->has('latitude')) {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $asset->locations()->create([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'created_by' => $request->user()->id,
            ]);
        } else {
            $asset->locations()->create([
                'created_by' => $request->user()->id,
            ]);
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
        $asset->load(['assetType', 'maintenanceItems', 'property.shape', 'property.zones', 'notes.photos', 'notes.createdBy', 'notes.views', 'currentLocation', 'locations']);
        $asset->notes->each(fn ($note) => $note->is_unread = $note->isUnreadBy(Auth::id()));

        $jobIds = $asset->jobs()->pluck('farm_jobs.id');

        $workSessions = $asset->workSessionsQuery()
            ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
            ->with('user')
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'recentJobs' => FarmJob::whereIn('id', $jobIds)->with('jobStatus')->latest()->take(5)->get(),
            'jobsCount' => $jobIds->count(),
            'bookedHours' => round($workSessions->sum('duration_in_hours'), 2),
            'workSessions' => $workSessions->map(fn ($session) => [
                'id' => $session->id,
                'user_name' => $session->user->name,
                'started_at' => $session->started_at,
                'hours' => $session->duration_in_hours,
            ]),
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
