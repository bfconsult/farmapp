<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\ChecklistTemplate;
use App\Models\Metric;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ManageController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');
        $currentProperty = $currentPropertyId ? Property::find($currentPropertyId) : null;
        $canManage = in_array(Auth::user()->roleOn($currentProperty), ['admin', 'manager'], true);

        $metrics = Metric::where('property_id', $currentPropertyId)
            ->with('latestMeasurement')
            ->orderBy('name')
            ->get();

        $checklistTemplates = $canManage
            ? ChecklistTemplate::where('property_id', $currentPropertyId)
                ->with('items')
                ->orderBy('name')
                ->get()
            : [];

        // Visible to every role, unlike checklistTemplates - only the
        // CRUD/location controls on top of this are canManage-gated.
        $assets = Asset::where('property_id', $currentPropertyId)
            ->with(['assetType', 'maintenanceItems'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Manage/Index', [
            'metrics' => $metrics,
            'checklistTemplates' => $checklistTemplates,
            'assets' => $assets,
            'assetTypes' => $canManage ? AssetType::orderBy('name')->get() : [],
            'canManage' => $canManage,
        ]);
    }
}
