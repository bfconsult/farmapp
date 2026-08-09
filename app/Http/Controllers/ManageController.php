<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\MaintenanceItem;
use App\Models\Metric;
use App\Models\MetricMeasurement;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ManageController extends Controller
{
    /**
     * The hub screen - just the 3 summary counts for its cards. The actual
     * datasets (metrics/checklistTemplates/assets/suppliers) live on their
     * own pages now (Metrics/Index, Manage/Checklists, Manage/Assets,
     * Manage/Suppliers).
     */
    public function index()
    {
        $currentPropertyId = session('current_property_id');
        $currentProperty = $currentPropertyId ? Property::find($currentPropertyId) : null;
        $currentUserRole = Auth::user()->roleOn($currentProperty);
        $canManage = in_array($currentUserRole, ['admin', 'manager'], true);
        // Approver is otherwise read-only everywhere else, but they can log
        // time on a worker's behalf - an explicit exception, not a general
        // widening of what an approver can do.
        $canReviewWorkSessions = in_array($currentUserRole, ['admin', 'manager', 'approver'], true);

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

        $suppliersCount = $canManage
            ? Supplier::where('property_id', $currentPropertyId)->count()
            : 0;

        return Inertia::render('Manage/Index', [
            'metricsTracked' => $metricsTracked,
            'metricsDue' => $metricsDue,
            'checklistTemplatesCount' => $checklistTemplatesCount,
            'assetsCount' => $assetsCount,
            'assetsOverdue' => $assetsOverdue,
            'suppliersCount' => $suppliersCount,
            'canManage' => $canManage,
            'canReviewWorkSessions' => $canReviewWorkSessions,
        ]);
    }
}
