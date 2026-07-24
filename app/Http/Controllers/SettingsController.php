<?php

namespace App\Http\Controllers;

use App\Models\Priority;
use App\Models\JobType;
use App\Models\JobStatus;
use App\Models\AssetType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    // Fixed 16-color palette offered for job status/priority/type pills.
    // Keys must match resources/js/Utils/pillColors.js exactly.
    public const PILL_COLORS = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'pink', 'rose',
    ];

    public function index()
    {
        return Inertia::render('Settings/Index', [
            'priorities' => Priority::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'assetTypes' => AssetType::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
            'billingBlockOptions' => User::BILLING_BLOCK_OPTIONS,
        ]);
    }

    public function updateBillingBlock(Request $request)
    {
        $validated = $request->validate([
            'billing_block_minutes' => 'nullable|in:'.implode(',', User::BILLING_BLOCK_OPTIONS),
        ]);

        Auth::user()->update($validated);

        return back();
    }

    // Priorities
    public function storePriority(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        Priority::create($validated);
        return back();
    }

    public function updatePriority(Request $request, Priority $priority)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        $priority->update($validated);
        return back();
    }

    public function destroyPriority(Priority $priority)
    {
        $priority->delete();
        return back();
    }

    // Job Types
    public function storeJobType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        JobType::create($validated);
        return back();
    }

    public function updateJobType(Request $request, JobType $jobType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        $jobType->update($validated);
        return back();
    }

    public function destroyJobType(JobType $jobType)
    {
        $jobType->delete();
        return back();
    }

    // Asset Types
    public function storeAssetType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        AssetType::create($validated);
        return back();
    }

    public function updateAssetType(Request $request, AssetType $assetType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $assetType->update($validated);
        return back();
    }

    public function destroyAssetType(AssetType $assetType)
    {
        $assetType->delete();
        return back();
    }

    // Suppliers
    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        Supplier::create($validated);
        return back();
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        $supplier->update($validated);
        return back();
    }

    public function destroySupplier(Supplier $supplier)
    {
        $supplier->delete();
        return back();
    }

    // Job Statuses
    public function storeJobStatus(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
            'can_book_time' => 'boolean',
            'is_in_progress_default' => 'boolean',
            'is_recurring_closed_default' => 'boolean',
            'is_finished_default' => 'boolean',
        ]);

        if ($validated['is_in_progress_default'] ?? false) {
            JobStatus::where('is_in_progress_default', true)->update(['is_in_progress_default' => false]);
        }

        if ($validated['is_recurring_closed_default'] ?? false) {
            JobStatus::where('is_recurring_closed_default', true)->update(['is_recurring_closed_default' => false]);
        }

        if ($validated['is_finished_default'] ?? false) {
            JobStatus::where('is_finished_default', true)->update(['is_finished_default' => false]);
        }

        JobStatus::create($validated);
        return back();
    }

    public function updateJobStatus(Request $request, JobStatus $jobStatus)
    {
        if ($jobStatus->is_protected) {
            abort(403, 'This status cannot be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
            'can_book_time' => 'boolean',
            'is_in_progress_default' => 'boolean',
            'is_recurring_closed_default' => 'boolean',
            'is_finished_default' => 'boolean',
        ]);

        // Only one status can be the in-progress default at a time.
        if ($validated['is_in_progress_default'] ?? false) {
            JobStatus::where('id', '!=', $jobStatus->id)
                ->where('is_in_progress_default', true)
                ->update(['is_in_progress_default' => false]);
        }

        // Only one status can be the recurring-closed default at a time.
        if ($validated['is_recurring_closed_default'] ?? false) {
            JobStatus::where('id', '!=', $jobStatus->id)
                ->where('is_recurring_closed_default', true)
                ->update(['is_recurring_closed_default' => false]);
        }

        // Only one status can be the finished default at a time.
        if ($validated['is_finished_default'] ?? false) {
            JobStatus::where('id', '!=', $jobStatus->id)
                ->where('is_finished_default', true)
                ->update(['is_finished_default' => false]);
        }

        $jobStatus->update($validated);
        return back();
    }

    public function destroyJobStatus(JobStatus $jobStatus)
    {
        if ($jobStatus->is_protected) {
            abort(403, 'This status cannot be deleted.');
        }

        $jobStatus->delete();
        return back();
    }
}