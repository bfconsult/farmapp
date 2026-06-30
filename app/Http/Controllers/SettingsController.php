<?php

namespace App\Http\Controllers;

use App\Models\Priority;
use App\Models\JobType;
use App\Models\JobStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'priorities' => Priority::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
        ]);
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
        ]);
        JobStatus::create($validated);
        return back();
    }

    public function updateJobStatus(Request $request, JobStatus $jobStatus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
            'can_book_time' => 'boolean',
        ]);
        $jobStatus->update($validated);
        return back();
    }

    public function destroyJobStatus(JobStatus $jobStatus)
    {
        $jobStatus->delete();
        return back();
    }
}