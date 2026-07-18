<?php

namespace App\Http\Controllers;

use App\Models\Priority;
use App\Models\JobType;
use App\Models\JobStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'priorities' => Priority::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
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
        ]);
        Priority::create($validated);
        return back();
    }

    public function updatePriority(Request $request, Priority $priority)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
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
        ]);
        JobType::create($validated);
        return back();
    }

    public function updateJobType(Request $request, JobType $jobType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $jobType->update($validated);
        return back();
    }

    public function destroyJobType(JobType $jobType)
    {
        $jobType->delete();
        return back();
    }

    // Job Statuses
    public function storeJobStatus(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
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