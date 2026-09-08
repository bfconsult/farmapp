<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\FarmJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            // Covers the case where a supplier emails/hands over an invoice
            // directly instead of using their Request-an-Invoice link (see
            // SupplierExpenseController, which handles that path) - stored
            // the same way, untouched, so a PDF isn't forced through the
            // image-only Photo pipeline.
            'invoice' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $farmJob->expenses()->create([
            ...$this->withInvoiceFile($validated),
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
            // Lets a manager attach the invoice after the fact too - e.g.
            // it arrived by email instead of through the supplier's own
            // link - or replace whichever file is already on there.
            'invoice' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $newValidated = $this->withInvoiceFile($validated);
        $oldInvoiceFile = isset($newValidated['invoice_file']) ? $expense->invoice_file : null;

        $expense->update($newValidated);

        // Replacing an already-attached invoice shouldn't leave the old
        // file behind - same cleanup as an avatar replacement.
        if ($oldInvoiceFile) {
            Storage::disk(config('filesystems.default'))->delete($oldInvoiceFile);
        }

        return back();
    }

    /**
     * Pulls the uploaded "invoice" file (if any) out of a validated array
     * and swaps it for the invoice_file/invoice_original_name columns that
     * are actually mass-assignable - mirrors SupplierExpenseController's
     * upload, storing the file untouched rather than through the image-only,
     * force-recompressed Photo pipeline.
     */
    private function withInvoiceFile(array $validated): array
    {
        $file = $validated['invoice'] ?? null;
        unset($validated['invoice']);

        if (!$file) {
            return $validated;
        }

        return [
            ...$validated,
            'invoice_file' => Storage::disk(config('filesystems.default'))->putFile('invoices', $file),
            'invoice_original_name' => $file->getClientOriginalName(),
        ];
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
