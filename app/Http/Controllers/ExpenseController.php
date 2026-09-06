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
            'date' => 'required|date',
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
        // An empty string from the form needs to actually become null for
        // "nullable" to take effect (Laravel's nullable rule doesn't treat
        // '' as null on its own) - same fix as SupplierExpenseController.
        $request->merge([
            'amount' => $request->filled('amount') ? $request->input('amount') : null,
        ]);

        // Nullable (unlike store() above) so a manager reviewing a
        // supplier-submitted expense with no amount yet can save other
        // details - like a description from the invoice - without being
        // forced to have the final figure ready in the same edit. Status
        // only changes via the explicit markReviewed() action below.
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'gst_inclusive' => 'boolean',
            'reimburse' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $expense->update($validated);

        return back();
    }

    /**
     * Explicit, separate action from update() - clears needs_review once the
     * property manager has actually entered a real amount from the
     * supplier's invoice. Not automatic on save, so a manager can save
     * partial progress (e.g. just the description) without accidentally
     * marking an incomplete expense as reviewed.
     */
    public function markReviewed(Expense $expense)
    {
        if ($expense->amount === null) {
            return back()->withErrors(['amount' => 'Add an amount before marking this reviewed.']);
        }

        $expense->update(['status' => Expense::COMPLETE]);

        return back()->with('success', 'Expense marked as reviewed.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back();
    }
}
