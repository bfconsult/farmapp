<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use Illuminate\Http\Request;

class ChecklistTemplateController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        ChecklistTemplate::create([
            ...$validated,
            'property_id' => session('current_property_id'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Checklist template created.');
    }

    public function update(Request $request, ChecklistTemplate $checklistTemplate)
    {
        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active', $checklistTemplate->is_active);

        $checklistTemplate->update($validated);

        return back()->with('success', 'Checklist template updated.');
    }

    public function destroy(ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->delete();

        return back()->with('success', 'Checklist template deleted. Checklists it already created are unaffected.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', ChecklistTemplate::TYPES),
        ]);
    }
}
