<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\Device;
use App\Models\Livestock;
use App\Models\MaintenanceItem;
use App\Models\Metric;
use App\Models\MetricMeasurement;
use App\Models\Mob;
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
        // Overdue by date (past its due date), or due by hours of use.
        $usageByAsset = [];
        $maintenanceItems = MaintenanceItem::whereHas('asset', fn ($q) => $q->where('property_id', $currentPropertyId))
            ->with('asset')
            ->get();
        $assetsOverdue = $maintenanceItems
            ->filter(function (MaintenanceItem $item) use (&$usageByAsset) {
                if ($item->next_due_date && $item->next_due_date->lt(today())) {
                    return true;
                }
                if ($item->next_due_usage_hours === null) {
                    return false;
                }
                $usage = $usageByAsset[$item->asset_id] ??= $item->asset->usageHours();

                return $usage >= $item->next_due_usage_hours;
            })
            ->count();

        // Maintenance jobs started but never finished - flagged here so one
        // left on a hidden status (e.g. "Cancelled") doesn't go unnoticed.
        $assetsJobsOpen = $maintenanceItems->filter(fn (MaintenanceItem $item) => $item->openJob() !== null)->count();

        $suppliersCount = $canManage
            ? Supplier::where('property_id', $currentPropertyId)->count()
            : 0;

        $sensorsCount = $canManage
            ? Device::where('property_id', $currentPropertyId)->count()
            : 0;

        $mobsCount = Mob::where('property_id', $currentPropertyId)->count();
        $livestockCount = Livestock::where('property_id', $currentPropertyId)->count();

        return Inertia::render('Manage/Index', [
            'metricsTracked' => $metricsTracked,
            'metricsDue' => $metricsDue,
            'checklistTemplatesCount' => $checklistTemplatesCount,
            'assetsCount' => $assetsCount,
            'assetsOverdue' => $assetsOverdue,
            'assetsJobsOpen' => $assetsJobsOpen,
            'suppliersCount' => $suppliersCount,
            'mobsCount' => $mobsCount,
            'livestockCount' => $livestockCount,
            'sensorsCount' => $sensorsCount,
            'canManage' => $canManage,
            'canReviewWorkSessions' => $canReviewWorkSessions,
        ]);
    }
}
