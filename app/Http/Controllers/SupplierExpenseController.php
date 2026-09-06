<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public, unauthenticated endpoint reached from a supplier's own quote
 * share link (see QuoteController::share()) - the app's first public write
 * rather than a read-only share page. Secured the same way every other
 * share link is (an unguessable share_token), plus a route-level rate
 * limit (see routes/web.php) since there's no login to fall back on.
 */
class SupplierExpenseController extends Controller
{
    public function store(Request $request, string $token)
    {
        $quote = Quote::where('share_token', $token)->firstOrFail();

        // Closes this off entirely unless the property actually asked for
        // an invoice on this accepted quote - not just "any accepted quote"
        // and not a declined/still-invited one.
        abort_unless($quote->status === Quote::ACCEPTED && $quote->invoice_requested_at, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'gst_inclusive' => 'boolean',
            // Stored untouched (see below) - must allow PDFs, so this can't
            // reuse Photo's image-only, force-recompressed pipeline.
            'invoice' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $file = $request->file('invoice');
        $path = Storage::disk(config('filesystems.default'))->putFile('invoices', $file);

        $quote->farmJob->expenses()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'gst_inclusive' => $validated['gst_inclusive'] ?? true,
            'reimburse' => false,
            'supplier_id' => $quote->supplier_id,
            'quote_id' => $quote->id,
            'created_by' => null,
            'invoice_file' => $path,
            'invoice_original_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', 'Invoice submitted.');
    }
}
