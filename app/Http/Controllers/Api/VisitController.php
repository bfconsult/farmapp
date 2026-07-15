<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSession;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * Batch-accepts completed property visits detected by the mobile app's
     * geofencing and turns each into a draft, ad-hoc WorkSession (no
     * specific job attached - that's an already-supported pattern) ready
     * for the user to review through the existing WorkSessions UI. Batched
     * because the app queues visits while offline and syncs when it can.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'visits' => 'required|array|min:1|max:100',
            'visits.*.external_uuid' => 'required|uuid|distinct',
            'visits.*.property_id' => 'required|integer',
            'visits.*.started_at' => 'required|date',
            'visits.*.ended_at' => 'required|date|after:visits.*.started_at',
            'visits.*.latitude' => 'nullable|numeric|between:-90,90',
            'visits.*.longitude' => 'nullable|numeric|between:-180,180',
            // Periodic on-site location samples, shown on the web app's
            // job-allocation screen (WorkSessions/Edit) - not sent for every
            // visit, only ones long enough for the mobile app's periodic
            // sampler to have fired.
            'visits.*.waypoints' => 'nullable|array',
            'visits.*.waypoints.*.lat' => 'required_with:visits.*.waypoints|numeric|between:-90,90',
            'visits.*.waypoints.*.lng' => 'required_with:visits.*.waypoints|numeric|between:-180,180',
            'visits.*.waypoints.*.recorded_at' => 'required_with:visits.*.waypoints|date',
        ]);

        $user = $request->user();
        $allowedPropertyIds = $user->properties()->pluck('properties.id')->all();

        $created = 0;
        $skippedDuplicate = 0;
        $skippedForbidden = 0;

        foreach ($validated['visits'] as $visit) {
            if (! in_array($visit['property_id'], $allowedPropertyIds, true)) {
                $skippedForbidden++;
                continue;
            }

            if (WorkSession::where('external_uuid', $visit['external_uuid'])->exists()) {
                $skippedDuplicate++;
                continue;
            }

            try {
                $workSession = WorkSession::create([
                    'user_id' => $user->id,
                    'property_id' => $visit['property_id'],
                    'farm_job_id' => null,
                    'started_at' => $visit['started_at'],
                    'ended_at' => $visit['ended_at'],
                    'latitude' => $visit['latitude'] ?? null,
                    'longitude' => $visit['longitude'] ?? null,
                    'status' => WorkSession::DRAFT,
                    'source' => 'auto_tracked',
                    'external_uuid' => $visit['external_uuid'],
                ]);

                if (! empty($visit['waypoints'])) {
                    $workSession->waypoints()->createMany(array_map(
                        fn ($waypoint) => [
                            'latitude' => $waypoint['lat'],
                            'longitude' => $waypoint['lng'],
                            'recorded_at' => $waypoint['recorded_at'],
                        ],
                        $visit['waypoints'],
                    ));
                }

                $created++;
            } catch (QueryException $e) {
                // Unique constraint on external_uuid - a near-simultaneous
                // retry from a flaky mobile connection lost the race against
                // the exists() check above. Same outcome either way: don't
                // duplicate, count it as already-synced.
                if (str_contains($e->getMessage(), 'external_uuid')) {
                    $skippedDuplicate++;
                    continue;
                }

                throw $e;
            }
        }

        return response()->json([
            'created' => $created,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_forbidden' => $skippedForbidden,
        ]);
    }
}
