<?php

namespace App\Http\Controllers;

use App\Models\FarmJob;
use App\Models\Priority;
use App\Models\JobType;
use App\Models\JobStatus;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FarmJobController extends Controller
{
    public function index(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        $view = $request->view ?? 'active';
    
        $query = Auth::user()->farmJobs()
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->with(['priority', 'jobType', 'jobStatus', 'property']);
    
        // Apply view filter
        match($view) {
            'active' => $query->whereHas('jobStatus', function ($q) {
                $q->where('can_book_time', true);
            })->orWhere(function ($q) use ($currentPropertyId) {
                $q->whereNull('job_status_id')
                  ->when($currentPropertyId, fn($q) => $q->where('property_id', $currentPropertyId));
            }),
            'completed' => $query->whereHas('jobStatus', function ($q) {
                $q->where('name', 'Completed');
            }),
            default => null,
        };
    
        // Status counts (always based on full unfiltered set)
        $allJobs = Auth::user()->farmJobs()
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->with('jobStatus')
            ->get();
    
        $counts = $allJobs->groupBy('job_status.name')->map->count();
    
        // Apply status filter on top of view
        if ($request->status) {
            $query->whereHas('jobStatus', function ($q) use ($request) {
                $q->where('name', $request->status);
            });
        }
    
        // Ordering
        $order = $request->order ?? 'latest';
        match($order) {
            'priority' => $query->join('priorities', 'farm_jobs.priority_id', '=', 'priorities.id')
                               ->orderBy('priorities.order', 'desc')
                               ->select('farm_jobs.*'),
            'status' => $query->join('job_statuses', 'farm_jobs.job_status_id', '=', 'job_statuses.id')
                             ->orderBy('job_statuses.order')
                             ->select('farm_jobs.*'),
            default => $query->latest(),
        };
    
        $jobs = $query->get();
    
        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'counts' => $counts,
            'currentStatus' => $request->status,
            'currentOrder' => $order,
            'currentView' => $view,
            'jobStatuses' => \App\Models\JobStatus::orderBy('order')->get(),
        ]);
    }

    public function create()
    {
        $currentProperty = Auth::user()->properties()
            ->find(session('current_property_id'));
    
        if (!$currentProperty) {
            return redirect()->route('properties.index')
                ->with('error', 'Please select a property first.');
        }
    
        return Inertia::render('Jobs/Create', [
            'priorities' => Priority::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'currentProperty' => $currentProperty,
        ]);
    }

   
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'priority_id' => 'nullable|exists:priorities,id',
            'job_type_id' => 'nullable|exists:job_types,id',
            'job_status_id' => 'nullable|exists:job_statuses,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
    
        $validated['user_id'] = Auth::id();
        $validated['property_id'] = session('current_property_id');
    
        $farmJob = FarmJob::create($validated);
    
        return redirect()->route('jobs.show', $farmJob)->with('addPhoto', true);
    }

    public function show(FarmJob $farmJob)
    {
        $farmJob->load(['priority', 'jobType', 'jobStatus', 'property', 'photos']);

        return Inertia::render('Jobs/Show', [
            'job' => $farmJob,
        ]);
    }

    public function edit(FarmJob $farmJob)
    {
        return Inertia::render('Jobs/Edit', [
            'job' => $farmJob,
            'priorities' => Priority::orderBy('order')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'properties' => Auth::user()->properties()->get(),
        ]);
    }

    public function update(Request $request, FarmJob $farmJob)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'priority_id' => 'nullable|exists:priorities,id',
            'job_type_id' => 'nullable|exists:job_types,id',
            'job_status_id' => 'nullable|exists:job_statuses,id',
            'property_id' => 'required|exists:properties,id',
        ]);

        $farmJob->update($validated);

        return redirect()->route('jobs.show', $farmJob);
    }

    public function destroy(FarmJob $farmJob)
    {
        $farmJob->delete();

        return redirect()->route('jobs.index');
    }
}