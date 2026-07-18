<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistTemplate;
use App\Models\FarmJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChecklistController extends Controller
{
    public function store(Request $request)
    {
        $currentPropertyId = session('current_property_id');

        $validated = $request->validate([
            'farm_job_id' => 'required|exists:farm_jobs,id',
            'checklist_template_id' => 'required|exists:checklist_templates,id',
        ]);

        $job = FarmJob::where('id', $validated['farm_job_id'])
            ->where('property_id', $currentPropertyId)
            ->firstOrFail();

        $template = ChecklistTemplate::where('id', $validated['checklist_template_id'])
            ->where('property_id', $currentPropertyId)
            ->firstOrFail();

        Checklist::attach($template, $job, $request->user());

        return back()->with('success', 'Checklist attached.');
    }

    public function show(Checklist $checklist)
    {
        $checklist->load(['items.photos', 'farmJob']);

        return Inertia::render('Checklists/Show', [
            'checklist' => $checklist,
        ]);
    }
}
