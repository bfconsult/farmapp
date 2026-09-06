<?php

namespace App\Http\Controllers;

use App\Mail\SupplierInvoiceRequest;
use App\Mail\SupplierJobLet;
use App\Mail\SupplierJobRequest;
use App\Models\FarmJob;
use App\Models\Quote;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QuoteController extends Controller
{
    /**
     * A supplier's own invite link - unique per quote (not the job's own
     * share_token, which every supplier invited to the same job would
     * otherwise share), so the shared job view can identify which supplier
     * is looking at it without them needing an account. Same curated,
     * read-only payload as FarmJobController::share() - no accept/decline
     * actions here, that's still done from inside the app after a call.
     */
    public function share(string $token)
    {
        $quote = Quote::where('share_token', $token)->with('supplier')->firstOrFail();
        $farmJob = $quote->farmJob;

        if ($farmJob->isVisibleTo(Auth::user())) {
            return redirect()->route('jobs.show', $farmJob);
        }

        $farmJob->load(['priority', 'jobType', 'jobStatus', 'property', 'photos']);

        return Inertia::render('Jobs/SharedView', [
            'job' => $farmJob->toSharePayload(),
            'logoUrl' => asset('favicon.svg'),
            'viewingSupplier' => $quote->supplier?->name,
            'canSubmitInvoice' => $quote->status === Quote::ACCEPTED && $quote->invoice_requested_at !== null,
            'quoteToken' => $token,
        ]);
    }

    public function store(Request $request, FarmJob $farmJob)
    {
        // Blocks the whole process rather than sending from a blank reply-to -
        // a supplier's reply needs somewhere real to land.
        if (!$farmJob->property->email) {
            return back()->withErrors(['supplier_id' => "{$farmJob->property->name} has no email on file - add one from the property's Edit page before inviting a supplier."]);
        }

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

    /**
     * Emails the accepted supplier a link to their existing share page,
     * where they can submit an invoice/expense directly - see
     * SupplierExpenseController. Callable repeatedly to resend; there's no
     * lock-out once requested, matching the rest of this feature's
     * deliberately loose design.
     */
    public function requestInvoice(Request $request, Quote $quote)
    {
        abort_unless($quote->status === Quote::ACCEPTED, 403);

        $quote->load(['farmJob.property', 'supplier']);

        // Same reasoning as store() - a supplier's reply (or the invoice
        // itself) needs somewhere real to land.
        if (!$quote->farmJob->property->email) {
            return back()->withErrors(['invoice_request' => "{$quote->farmJob->property->name} has no email on file - add one from the property's Edit page first."]);
        }

        if (!$quote->supplier?->email) {
            return back()->withErrors(['invoice_request' => "{$quote->supplier?->name} has no email on file - add one from Manage \u{2192} Suppliers first."]);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $quote->update(['invoice_requested_at' => now()]);

        Mail::to($quote->supplier->email)->send(new SupplierInvoiceRequest($quote, $validated['message'] ?? null));

        return back()->with('success', 'Invoice request sent.');
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
