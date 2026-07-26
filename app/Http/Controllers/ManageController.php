<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\MaintenanceItem;
use App\Models\Metric;
use App\Models\MetricMeasurement;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ManageController extends Controller
{
    /**
     * The hub screen - just the 3 summary counts for its cards. The actual
     * datasets (metrics/checklistTemplates/assets) live on their own pages
     * now (Metrics/Index, Manage/Checklists, Manage/Assets).
     */
    public function index()
    {
        $currentPropertyId = session('current_property_id');
        $currentProperty = $currentPropertyId ? Property::find($currentPropertyId) : null;
        $canManage = in_array(Auth::user()->roleOn($currentProperty), ['admin', 'manager'], true);

        $metricsTracked = Metric::where('property_id', $currentPropertyId)->count();
        $metricsDue = Metric::where('property_id', $currentPropertyId)
            ->whereHas('latestMeasurement', fn ($q) => $q->where('status', MetricMeasurement::INCOMPLETE))
            ->count();

        $checklistTemplatesCount = $canManage
            ? ChecklistTemplate::where('property_id', $currentPropertyId)->count()
            : 0;

        $assetsCount = Asset::where('property_id', $currentPropertyId)->count();
        $assetsOverdue = MaintenanceItem::whereHas('asset', fn ($q) => $q->where('property_id', $currentPropertyId))
            ->where('next_due_date', '<', now()->toDateString())
            ->count();

        return Inertia::render('Manage/Index', [
            'metricsTracked' => $metricsTracked,
            'metricsDue' => $metricsDue,
            'checklistTemplatesCount' => $checklistTemplatesCount,
            'assetsCount' => $assetsCount,
            'assetsOverdue' => $assetsOverdue,
            'canManage' => $canManage,
        ]);
    }
}
