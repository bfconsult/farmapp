<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Http\Request;

class ChecklistTemplateItemController extends Controller
{
    public function store(Request $request, ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->items()->create($this->validated($request));

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, ChecklistTemplateItem $checklistTemplateItem)
    {
        $checklistTemplateItem->update($this->validated($request));

        return back()->with('success', 'Item updated.');
    }

    public function destroy(ChecklistTemplateItem $checklistTemplateItem)
    {
        $checklistTemplateItem->delete();

        return back()->with('success', 'Item deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'evidence' => 'nullable|string|max:255',
        ]);
        $validated['photo_required'] = $request->boolean('photo_required');

        return $validated;
    }
}
