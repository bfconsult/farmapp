<?php

namespace App\Http\Controllers;

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

        return Inertia::render('Manage/Index', [
            'metrics' => $metrics,
            'checklistTemplates' => $checklistTemplates,
            'canManage' => $canManage,
        ]);
    }
}
