<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Device;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\WorkSession;
use App\Services\Lorawan\SensorKinds;
use App\Services\Lorawan\TankActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

        // Flags assets with an item due by hours of use (the list's date
        // badge can't show those on its own), and assets with a maintenance
        // job that was started but never finished - e.g. left on a
        // "Cancelled" status that's hidden from the Jobs list.
        $assets->each(function (Asset $asset) {
            $asset->maintenance_job_open = $asset->maintenanceItems->contains(fn ($item) => $item->openJob() !== null);

            $usage = $asset->maintenanceItems->contains(fn ($item) => $item->repeat_usage_hours !== null)
                ? $asset->usageHours()
                : 0;
            $asset->usage_maintenance_due = $asset->maintenanceItems->contains(
                fn ($item) => $item->next_due_usage_hours !== null && $usage >= $item->next_due_usage_hours
            );
        });

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
        $asset->update($this->validated($request, $asset));

        return back()->with('success', 'Asset updated.');
    }

    /**
     * Records a new current location for this asset - every call inserts a
     * fresh AssetLocation row (including "clear", which inserts an all-null
     * row) rather than overwriting one, so location history accumulates. An
     * asset has at most one of a point location, a shape or a line at a time
     * within a single row - saving one clears the others, mirroring how
     * Properties/Shape.jsx saves a zone's polygon immediately on draw, no
     * separate Save step.
     */
    public function updateLocation(Request $request, Asset $asset)
    {
        if ($request->has('line')) {
            // A fence, pipe or track: a run of [lat, lng] points.
            $validated = $request->validate([
                'line' => 'required|array|min:2',
                'line.*' => 'array|size:2',
                'line.*.0' => 'numeric|between:-90,90',
                'line.*.1' => 'numeric|between:-180,180',
            ]);

            $asset->locations()->create([
                'line' => array_map(fn ($point) => [(float) $point[0], (float) $point[1]], $validated['line']),
                'created_by' => $request->user()->id,
            ]);
        } elseif ($request->has('shape')) {
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

        // The property's other lines (fences, pipes...), so a new one can be
        // drawn to meet them exactly.
        $otherLines = Asset::where('property_id', $asset->property_id)
            ->whereKeyNot($asset->id)
            ->with('currentLocation')
            ->get()
            ->filter(fn (Asset $other) => $other->currentLocation?->line)
            ->map(fn (Asset $other) => ['id' => $other->id, 'name' => $other->name, 'line' => $other->currentLocation->line])
            ->values();
        $asset->notes->each(fn ($note) => $note->is_unread = $note->isUnreadBy(Auth::id()));

        $jobIds = $asset->jobs()->pluck('farm_jobs.id');

        $counted = [WorkSession::FINALISED, WorkSession::APPROVED];

        $usageSessions = $asset->workSessions()
            ->whereIn('status', $counted)
            ->with('user')
            ->get();

        $maintenanceSessions = $asset->maintenanceSessionsQuery()
            ->whereIn('status', $counted)
            ->with('user')
            ->get();

        $usageHours = round($usageSessions->sum('duration_in_hours'), 2);
        $asset->maintenanceItems->each(function ($item) use ($usageHours) {
            $item->is_due = $item->isDue($usageHours);
            $openJob = $item->openJob();
            $item->open_job = $openJob ? [
                'id' => $openJob->id,
                'status' => $openJob->jobStatus?->name,
                'days_open' => (int) $openJob->created_at->diffInDays(now()),
            ] : null;
        });

        $toRow = fn (string $kind) => fn ($session) => [
            'id' => $session->id,
            'kind' => $kind,
            'user_name' => $session->user->name,
            'started_at' => $session->started_at,
            'hours' => $session->duration_in_hours,
        ];

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'otherLines' => $otherLines,
            // For the edit form's type picker (e.g. to make a Fixed asset a fence).
            'assetTypes' => AssetType::where('property_id', $asset->property_id)->orderBy('name')->get(['id', 'name', 'kind']),
            'sensors' => $this->fittedSensors($asset),
            'recentJobs' => FarmJob::whereIn('id', $jobIds)->with('jobStatus')->latest()->take(5)->get(),
            'jobsCount' => $jobIds->count(),
            'usageHours' => $usageHours,
            'maintenanceHours' => round($maintenanceSessions->sum('duration_in_hours'), 2),
            'workSessions' => $usageSessions->map($toRow('usage'))
                ->concat($maintenanceSessions->map($toRow('maintenance')))
                ->sortByDesc('started_at')
                ->values(),
        ]);
    }

    public function jobHistory(Asset $asset)
    {
        return Inertia::render('Assets/JobHistory', [
            'asset' => $asset,
            'jobs' => $asset->jobs()->with('jobStatus')->latest('farm_jobs.created_at')->get(),
        ]);
    }

    /**
     * The sensors fitted to it, each with what it says now: {id, name,
     * kind, kind_label, state, status (its kind's status, e.g. a tank's
     * litres or a gate's open-since), last_seen_at}.
     */
    private function fittedSensors(Asset $asset): array
    {
        $timezone = Auth::user()->displayTimezone();

        return $asset->devices()
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'kind' => $device->kind,
                'kind_label' => SensorKinds::label($device->kind),
                'state' => SensorKinds::state($device),
                'status' => SensorKinds::activity($device, $timezone)?->status(),
                'capacity' => $device->kind === 'tank' ? TankActivity::capacity($device) : null,
                'last_seen_at' => $device->last_seen_at,
            ])
            ->all();
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'value' => 'nullable|numeric|min:0',
            'details' => 'nullable|array',
            'details.fence_type' => ['nullable', Rule::in(array_keys(Asset::FENCE_TYPES))],
            'details.wires' => 'nullable|integer|min:1|max:20',
            'details.capacity' => 'nullable|numeric|min:0|max:100000000',
            'details.vegetation_type' => ['nullable', Rule::in(array_keys(Asset::VEGETATION_TYPES))],
            'details.trough_size' => ['nullable', Rule::in(array_keys(Asset::TROUGH_SIZES))],
        ]);

        // Only its kind's details are kept, so they go if its type changes.
        $typeId = array_key_exists('asset_type_id', $validated) ? $validated['asset_type_id'] : $asset?->asset_type_id;
        $kind = $typeId ? AssetType::whereKey($typeId)->value('kind') : null;
        if (array_key_exists('details', $validated)) {
            $validated['details'] = Asset::detailsFor($kind, $validated['details']);
        } elseif ($asset) {
            $validated['details'] = Asset::detailsFor($kind, $asset->details);
        }

        return $validated;
    }
}
