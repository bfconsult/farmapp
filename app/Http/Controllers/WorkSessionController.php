<?php

namespace App\Http\Controllers;

use App\Models\FarmJob;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkSessionController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');

        $sessions = Auth::user()->workSessions()
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->with(['farmJob', 'property'])
            ->latest('started_at')
            ->get()
            ->map(function ($session) {
                $session->duration_in_hours = $session->duration_in_hours;
                $session->billing_amount = $session->billing_amount;
                return $session;
            });

        return Inertia::render('WorkSessions/Index', [
            'sessions' => $sessions,
            'activeSession' => Auth::user()->workSessions()
                ->whereNull('ended_at')
                ->with('farmJob')
                ->first(),
        ]);
    }

    public function create()
    {
        $currentPropertyId = session('current_property_id');
    
        $plannedJobs = Auth::user()->farmJobs()
        ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
            $query->where('property_id', $currentPropertyId);
        })
        ->where(function ($query) {
            $query->whereHas('jobStatus', function ($q) {
                $q->where('can_book_time', true);
            })->orWhereNull('job_status_id');
        })
        ->get();
    
        return Inertia::render('WorkSessions/Create', [
            'plannedJobs' => $plannedJobs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'started_at' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['property_id'] = session('current_property_id');
        $validated['started_at'] = $validated['started_at'] ?? now();

        $session = WorkSession::create($validated);

        return redirect()->route('work-sessions.show', $session)
            ->with('addPhoto', true);
    }

    public function show(WorkSession $workSession)
    {
        $workSession->load(['farmJob', 'property', 'photos']);

        return Inertia::render('WorkSessions/Show', [
            'session' => $workSession,
            'durationInHours' => $workSession->duration_in_hours,
            'billingAmount' => $workSession->billing_amount,
        ]);
    }

    public function edit(WorkSession $workSession)
    {
        $plannedJobs = Auth::user()->farmJobs()
            ->where('property_id', $workSession->property_id)
            ->get();

        return Inertia::render('WorkSessions/Edit', [
            'session' => $workSession,
            'plannedJobs' => $plannedJobs,
        ]);
    }

    public function update(Request $request, WorkSession $workSession)
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $workSession->update($validated);

        return redirect()->route('work-sessions.show', $workSession);
    }

    public function destroy(WorkSession $workSession)
    {
        $workSession->delete();

        return redirect()->route('work-sessions.index');
    }

    public function stop(WorkSession $workSession)
    {
        $workSession->update(['ended_at' => now()]);

        return redirect()->route('work-sessions.show', $workSession);
    }
}