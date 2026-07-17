<?php

namespace App\Http\Controllers;

use App\Models\MetricMeasurement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MetricMeasurementController extends Controller
{
    public function show(MetricMeasurement $metricMeasurement)
    {
        $metricMeasurement->load('photos');

        return Inertia::render('MetricMeasurements/Show', [
            'measurement' => $metricMeasurement,
        ]);
    }

    public function update(Request $request, MetricMeasurement $metricMeasurement)
    {
        $validated = $request->validate([
            'value_number' => $metricMeasurement->answer_type === 'number' ? 'nullable|numeric' : 'prohibited',
            'value_text' => $metricMeasurement->answer_type === 'text' ? 'nullable|string' : 'prohibited',
            'status' => 'required|in:' . implode(',', MetricMeasurement::STATUSES),
        ]);

        if ($validated['status'] === MetricMeasurement::COMPLETE && $metricMeasurement->status !== MetricMeasurement::COMPLETE) {
            $validated['completed_by'] = $request->user()->id;
            $validated['completed_at'] = now();
        } elseif ($validated['status'] === MetricMeasurement::INCOMPLETE) {
            $validated['completed_by'] = null;
            $validated['completed_at'] = null;
        }

        $metricMeasurement->update($validated);

        return back()->with('success', 'Measurement updated.');
    }
}
