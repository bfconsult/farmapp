<?php

namespace App\Http\Middleware;

use App\Models\Metric;
use App\Models\MetricMeasurement;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
{
    $user = $request->user();
    $properties = $user ? $user->properties()->get() : collect();

    $currentPropertyId = session('current_property_id');

    // Auto-select property if none set
    if (!$currentPropertyId && $properties->count() > 0) {
        if ($properties->count() === 1) {
            $currentPropertyId = $properties->first()->id;
        } else {
            // Select property with most jobs
            $currentPropertyId = $user->farmJobs()
                ->selectRaw('property_id, count(*) as job_count')
                ->groupBy('property_id')
                ->orderByDesc('job_count')
                ->first()
                ?->property_id ?? $properties->first()->id;
        }
        session(['current_property_id' => $currentPropertyId]);
    }

    $currentProperty = $user && $currentPropertyId
        ? $user->properties()->find($currentPropertyId)
        : null;

    // Drives the red dot on the Metrics nav icon - true if any active
    // metric's current (latest) measurement is still incomplete.
    $hasIncompleteMetrics = $currentProperty
        ? Metric::where('property_id', $currentProperty->id)
            ->where('is_active', true)
            ->whereHas('latestMeasurement', fn ($q) => $q->where('status', MetricMeasurement::INCOMPLETE))
            ->exists()
        : false;

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $user,
        ],
        'properties' => $properties,
        'currentProperty' => $currentProperty,
        'currentUserRole' => $user && $currentProperty ? $user->roleOn($currentProperty) : null,
        'hasIncompleteMetrics' => $hasIncompleteMetrics,
        'flash' => [
            'addPhoto' => session('addPhoto'),
            'error' => session('error'),
            'success' => session('success'),
        ],
    ];
}
}
