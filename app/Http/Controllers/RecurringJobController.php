<?php

namespace App\Http\Controllers;

use App\Models\JobType;
use App\Models\Priority;
use App\Models\RecurringJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RecurringJobController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');

        $recurringJobs = RecurringJob::where('property_id', $currentPropertyId)
            ->withCount('instances')
            ->orderBy('name')
            ->get();

        return Inertia::render('RecurringJobs/Index', [
            'recurringJobs' => $recurringJobs,
            'jobTypes' => JobType::orderBy('name')->get(),
            'priorities' => Priority::orderBy('order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['property_id'] = session('current_property_id');
        $validated['created_by'] = Auth::id();
        $validated['is_active'] = true;

        RecurringJob::create($validated);

        return back()->with('success', 'Recurring job created.');
    }

    public function update(Request $request, RecurringJob $recurringJob)
    {
        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active', $recurringJob->is_active);

        $recurringJob->update($validated);

        return back()->with('success', 'Recurring job updated.');
    }

    public function destroy(RecurringJob $recurringJob)
    {
        $recurringJob->delete();

        return back()->with('success', 'Recurring job deleted. Jobs it already created are unaffected.');
    }

    /**
     * starts_on is deliberately excluded from update() — changing it once
     * instances already exist would make it ambiguous which period is current.
     */
    private function validated(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'job_type_id' => 'nullable|exists:job_types,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'interval' => 'required|in:' . implode(',', RecurringJob::INTERVALS),
        ];

        if ($request->routeIs('recurring-jobs.store')) {
            $rules['starts_on'] = 'required|date';
        }

        return $request->validate($rules);
    }
}
