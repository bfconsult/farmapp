<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\FarmJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function store(Request $request, FarmJob $farmJob)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'gst_inclusive' => 'boolean',
            'reimburse' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $farmJob->expenses()->create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return back();
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'gst_inclusive' => 'boolean',
            'reimburse' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $expense->update($validated);

        return back();
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back();
    }
}
