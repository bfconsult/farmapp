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

    /**
     * Lets someone recording a measurement jump straight to a past period's
     * measurement instead (e.g. catching up on a metric missed last month)
     * rather than hunting for it on the History page - creating it first if
     * that period was never opened (the scheduler missed it, or the metric
     * didn't exist yet), using Metric::periodStartFor()'s calendar-aligned
     * period for the given date.
     */
    public function measurementForDate(Request $request, Metric $metric)
    {
        abort_unless($metric->property_id === (int) session('current_property_id'), 404);

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        $date = \Carbon\Carbon::parse($validated['date']);

        $measurement = $metric->measurements()
            ->where('period_start', '<=', $date)
            ->where('period_end', '>=', $date)
            ->first()
            ?? $metric->createMeasurement($metric->periodStartFor($date));

        return redirect()->route('metric-measurements.show', $measurement);
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
