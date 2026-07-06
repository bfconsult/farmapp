<?php

namespace App\Http\Controllers;

use App\Models\JobType;
use App\Models\Priority;
use App\Models\RecurringJob;
use Illuminate\Http\Request;
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

    /**
     * Creating a recurring job happens on the normal job-creation screen (see
     * FarmJobController::store()) so the first instance is created in the
     * same action — this controller only manages templates after that.
     */
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
     * starts_on is deliberately excluded — changing it once instances already
     * exist would make it ambiguous which period is current.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'job_type_id' => 'nullable|exists:job_types,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'interval' => 'required|in:' . implode(',', RecurringJob::INTERVALS),
        ]);
    }
}
