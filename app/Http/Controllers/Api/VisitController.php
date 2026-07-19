<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSession;
use Carbon\Carbon;
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
            // Not `after:visits.*.started_at` here - a wildcard-to-wildcard
            // comparison rule makes one invalid item fail validation for the
            // *entire* request (Laravel validates the whole array as a unit),
            // which would block every other visit in the same batch from
            // syncing too. Checked per-item in the loop below instead, where
            // an invalid item can be skipped on its own.
            'visits.*.ended_at' => 'required|date',
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
        $extended = 0;
        $skippedDuplicate = 0;
        $skippedForbidden = 0;
        $skippedInvalid = 0;

        foreach ($validated['visits'] as $visit) {
            if (! in_array($visit['property_id'], $allowedPropertyIds, true)) {
                $skippedForbidden++;
                continue;
            }

            // A near-instant visit (GPS jitter bouncing in and out within the
            // same second) can end up with ended_at <= started_at once
            // truncated to whole seconds in storage - not worth a session
            // either way, so skip it rather than reject the whole batch.
            if (Carbon::parse($visit['ended_at'])->lte(Carbon::parse($visit['started_at']))) {
                $skippedInvalid++;
                continue;
            }

            $existing = WorkSession::where('external_uuid', $visit['external_uuid'])->first();

            if ($existing) {
                // The mobile app coalesces a visit that re-opens shortly after
                // closing (GPS jitter bouncing across a small geofence, e.g. a
                // tight non-working zone) into the same external_uuid with a
                // later ended_at, rather than starting a new visit - see
                // VisitRepository.recordEntry. Only extend a still-draft
                // session: once reviewed/finalised in the web app, a later
                // sync shouldn't silently change it.
                if ($existing->status === WorkSession::DRAFT && Carbon::parse($visit['ended_at'])->gt($existing->ended_at)) {
                    $existing->update(['ended_at' => $visit['ended_at']]);
                    $existing->waypoints()->delete();
                    if (! empty($visit['waypoints'])) {
                        $existing->waypoints()->createMany(array_map(
                            fn ($waypoint) => [
                                'latitude' => $waypoint['lat'],
                                'longitude' => $waypoint['lng'],
                                'recorded_at' => $waypoint['recorded_at'],
                            ],
                            $visit['waypoints'],
                        ));
                    }
                    $extended++;
                } else {
                    $skippedDuplicate++;
                }
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
            'extended' => $extended,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_forbidden' => $skippedForbidden,
            'skipped_invalid' => $skippedInvalid,
        ]);
    }
}
