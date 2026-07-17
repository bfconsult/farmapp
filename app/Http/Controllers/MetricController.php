<?php

namespace App\Http\Controllers;

use App\Models\Metric;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MetricController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');

        $metrics = Metric::where('property_id', $currentPropertyId)
            ->with('latestMeasurement')
            ->orderBy('name')
            ->get();

        return Inertia::render('Metrics/Index', [
            'metrics' => $metrics,
        ]);
    }

    public function history(Metric $metric)
    {
        $measurements = $metric->measurements()
            ->with('photos')
            ->orderByDesc('period_start')
            ->get();

        return Inertia::render('Metrics/History', [
            'metric' => $metric,
            'measurements' => $measurements,
        ]);
    }

    /**
     * The first measurement is opened immediately so a newly created metric
     * has something to fill in right away, rather than waiting for tomorrow's
     * scheduler run (see GenerateMetricMeasurements for the ongoing case).
     */
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $metric = Metric::create([
            ...$validated,
            'property_id' => session('current_property_id'),
            'created_by' => $request->user()->id,
        ]);

        $metric->createMeasurement(now()->startOfDay());

        return back()->with('success', 'Metric created.');
    }

    public function update(Request $request, Metric $metric)
    {
        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active', $metric->is_active);

        $metric->update($validated);

        return back()->with('success', 'Metric updated.');
    }

    public function destroy(Metric $metric)
    {
        $metric->delete();

        return back()->with('success', 'Metric deleted. Measurements it already created are unaffected.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reporting_period' => 'required|in:' . implode(',', Metric::REPORTING_PERIODS),
            'answer_type' => 'required|in:' . implode(',', Metric::ANSWER_TYPES),
        ]);
    }
}
