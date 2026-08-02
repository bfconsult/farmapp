<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $currentPropertyId = session('current_property_id');

        $validated = $request->validate([
            'body' => 'required|string',
            'asset_id' => ['nullable', Rule::exists('assets', 'id')->where('property_id', $currentPropertyId)],
            'job_id' => ['nullable', Rule::exists('farm_jobs', 'id')->where('property_id', $currentPropertyId)],
            'work_session_id' => ['nullable', Rule::exists('work_sessions', 'id')->where('property_id', $currentPropertyId)],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $hasAsset = filled($validated['asset_id'] ?? null);
        $hasJob = filled($validated['job_id'] ?? null);
        $hasWorkSession = filled($validated['work_session_id'] ?? null);
        $hasLocation = filled($validated['latitude'] ?? null) && filled($validated['longitude'] ?? null);

        abort_unless(($hasAsset + $hasJob + $hasWorkSession + $hasLocation) === 1, 422, 'A note must be linked to exactly one of an asset, a job, a work session, or a map location.');

        Note::create([
            'property_id' => $currentPropertyId,
            'asset_id' => $validated['asset_id'] ?? null,
            'job_id' => $validated['job_id'] ?? null,
            'work_session_id' => $validated['work_session_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'body' => $validated['body'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note)
    {
        $note->update($request->validate(['body' => 'required|string']));

        return back()->with('success', 'Note updated.');
    }

    /**
     * Asset-notes and job-notes inherit their parent's own location - only
     * location-notes can be repositioned here.
     */
    public function updateLocation(Request $request, Note $note)
    {
        abort_if($note->asset_id !== null || $note->job_id !== null || $note->work_session_id !== null, 422, "This note doesn't have its own map location.");

        $note->update($request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]));

        return back()->with('success', 'Location updated.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /**
     * One row per (note, user) - marks a note read for whoever just opened
     * it (clicked a map pin, or tapped an inline note), not a full view log.
     */
    public function markSeen(Note $note)
    {
        NoteView::updateOrCreate(
            ['note_id' => $note->id, 'user_id' => Auth::id()],
            ['viewed_at' => now()],
        );

        return back();
    }
}
