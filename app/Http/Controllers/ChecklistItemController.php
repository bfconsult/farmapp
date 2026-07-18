<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function update(Request $request, ChecklistItem $checklistItem)
    {
        $validated = $request->validate([
            'is_checked' => 'sometimes|boolean',
            'evidence_value' => 'nullable|string',
        ]);

        $isChecked = $validated['is_checked'] ?? $checklistItem->is_checked;

        if ($isChecked && !$checklistItem->is_checked && $checklistItem->photo_required && $checklistItem->photos()->doesntExist()) {
            return back()->withErrors([
                'is_checked' => 'A photo is required for this item before it can be checked.',
            ]);
        }

        if (array_key_exists('is_checked', $validated)) {
            if ($isChecked) {
                $validated['checked_by'] = $request->user()->id;
                $validated['checked_at'] = now();
            } else {
                $validated['checked_by'] = null;
                $validated['checked_at'] = null;
            }
        }

        $checklistItem->update($validated);
        $checklistItem->checklist->refreshStatus();

        return back()->with('success', 'Item updated.');
    }
}
