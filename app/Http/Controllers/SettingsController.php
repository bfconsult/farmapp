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
        $currentPropertyId = session('current_property_id');

        return Inertia::render('Settings/Index', [
            'priorities' => Priority::where('property_id', $currentPropertyId)->orderBy('order')->get(),
            'jobTypes' => JobType::where('property_id', $currentPropertyId)->orderBy('name')->get(),
            'jobStatuses' => JobStatus::where('property_id', $currentPropertyId)->orderBy('order')->get(),
            'assetTypes' => AssetType::where('property_id', $currentPropertyId)->orderBy('name')->get(),
            'suppliers' => Supplier::where('property_id', $currentPropertyId)->orderBy('name')->get(),
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

    // Priorities - scoped to the current property; see the Job Statuses
    // section below for why (priorities used to be a single list shared by
    // every property in the app).
    public function storePriority(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        Priority::create([...$validated, 'property_id' => session('current_property_id')]);
        return back();
    }

    public function updatePriority(Request $request, Priority $priority)
    {
        if ($priority->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

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
        if ($priority->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $priority->delete();
        return back();
    }

    // Job Types - scoped to the current property, same as Priorities.
    public function storeJobType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        JobType::create([...$validated, 'property_id' => session('current_property_id')]);
        return back();
    }

    public function updateJobType(Request $request, JobType $jobType)
    {
        if ($jobType->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|in:'.implode(',', self::PILL_COLORS),
        ]);
        $jobType->update($validated);
        return back();
    }

    public function destroyJobType(JobType $jobType)
    {
        if ($jobType->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $jobType->delete();
        return back();
    }

    // Asset Types - scoped to the current property, same as Priorities.
    public function storeAssetType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        AssetType::create([...$validated, 'property_id' => session('current_property_id')]);
        return back();
    }

    public function updateAssetType(Request $request, AssetType $assetType)
    {
        if ($assetType->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $assetType->update($validated);
        return back();
    }

    public function destroyAssetType(AssetType $assetType)
    {
        if ($assetType->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $assetType->delete();
        return back();
    }

    // Suppliers - scoped to the current property, same as Priorities.
    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        Supplier::create([...$validated, 'property_id' => session('current_property_id')]);
        return back();
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        if ($supplier->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

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
        if ($supplier->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $supplier->delete();
        return back();
    }

    // Job Statuses - scoped to the current property; job_statuses used to be
    // a single list shared by every property in the app (see the
    // scope_job_statuses_to_property migration), so every query/guard here
    // now needs a property check that wasn't previously needed.
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

        $currentPropertyId = session('current_property_id');

        if ($validated['is_in_progress_default'] ?? false) {
            JobStatus::where('property_id', $currentPropertyId)->where('is_in_progress_default', true)
                ->update(['is_in_progress_default' => false]);
        }

        if ($validated['is_recurring_closed_default'] ?? false) {
            JobStatus::where('property_id', $currentPropertyId)->where('is_recurring_closed_default', true)
                ->update(['is_recurring_closed_default' => false]);
        }

        if ($validated['is_finished_default'] ?? false) {
            JobStatus::where('property_id', $currentPropertyId)->where('is_finished_default', true)
                ->update(['is_finished_default' => false]);
        }

        JobStatus::create([...$validated, 'property_id' => $currentPropertyId]);
        return back();
    }

    public function updateJobStatus(Request $request, JobStatus $jobStatus)
    {
        if ($jobStatus->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

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

        // Only one status per property can be the in-progress default at a time.
        if ($validated['is_in_progress_default'] ?? false) {
            JobStatus::where('property_id', $jobStatus->property_id)
                ->where('id', '!=', $jobStatus->id)
                ->where('is_in_progress_default', true)
                ->update(['is_in_progress_default' => false]);
        }

        // Only one status per property can be the recurring-closed default at a time.
        if ($validated['is_recurring_closed_default'] ?? false) {
            JobStatus::where('property_id', $jobStatus->property_id)
                ->where('id', '!=', $jobStatus->id)
                ->where('is_recurring_closed_default', true)
                ->update(['is_recurring_closed_default' => false]);
        }

        // Only one status per property can be the finished default at a time.
        if ($validated['is_finished_default'] ?? false) {
            JobStatus::where('property_id', $jobStatus->property_id)
                ->where('id', '!=', $jobStatus->id)
                ->where('is_finished_default', true)
                ->update(['is_finished_default' => false]);
        }

        $jobStatus->update($validated);
        return back();
    }

    public function destroyJobStatus(JobStatus $jobStatus)
    {
        if ($jobStatus->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        if ($jobStatus->is_protected) {
            abort(403, 'This status cannot be deleted.');
        }

        $jobStatus->delete();
        return back();
    }
}