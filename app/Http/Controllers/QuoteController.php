<?php

namespace App\Http\Controllers;

use App\Mail\SupplierJobLet;
use App\Mail\SupplierJobRequest;
use App\Models\FarmJob;
use App\Models\Quote;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function store(Request $request, FarmJob $farmJob)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('property_id', $farmJob->property_id)],
            'requires_quote' => 'required|boolean',
            'message' => 'nullable|string|max:2000',
        ]);

        $supplier = Supplier::find($validated['supplier_id']);
        if (!$supplier->email) {
            return back()->withErrors(['supplier_id' => "{$supplier->name} has no email on file - add one from Manage \u{2192} Suppliers first."]);
        }

        $quote = $farmJob->quotes()->create([
            ...$validated,
            'created_by' => Auth::id(),
            'status' => Quote::INVITED,
            'invited_at' => now(),
        ]);

        $quote->load(['farmJob.property', 'supplier']);

        Mail::to($supplier->email)->send(new SupplierJobRequest($quote));

        return back()->with('success', 'Supplier invited.');
    }

    public function accept(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'amount' => $quote->requires_quote ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
        ]);

        $quote->update([
            'status' => Quote::ACCEPTED,
            'amount' => $validated['amount'] ?? $quote->amount,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Quote accepted.');
    }

    public function decline(Quote $quote)
    {
        $quote->update([
            'status' => Quote::DECLINED,
            'decided_at' => now(),
        ]);

        return back();
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return back();
    }

    /**
     * Once one supplier's quote is accepted, this notifies every other
     * still-invited supplier on the same job that it's gone elsewhere - a
     * deliberate, separate action rather than something that fires
     * automatically on acceptance (the property owner may want to hold off,
     * or decide manually who else still needs telling).
     */
    public function notifyOthers(FarmJob $farmJob)
    {
        $others = $farmJob->quotes()
            ->where('status', Quote::INVITED)
            ->with(['supplier', 'farmJob.property'])
            ->get();

        foreach ($others as $quote) {
            if ($quote->supplier?->email) {
                Mail::to($quote->supplier->email)->send(new SupplierJobLet($quote));
            }
            $quote->update(['status' => Quote::DECLINED, 'decided_at' => now()]);
        }

        return back()->with('success', 'Other suppliers notified.');
    }
}
