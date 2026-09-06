<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');

        return Inertia::render('Manage/Suppliers/Index', [
            'suppliers' => Supplier::where('property_id', $currentPropertyId)->orderBy('name')->get(),
        ]);
    }

    /**
     * Reuses the Edit page with no supplier - one form component handles
     * both create and update, the same way Properties/Edit.jsx does.
     */
    public function create()
    {
        return Inertia::render('Manage/Suppliers/Edit', [
            'supplier' => null,
        ]);
    }

    public function store(Request $request)
    {
        Supplier::create([
            ...$this->validated($request),
            'property_id' => session('current_property_id'),
        ]);

        return redirect()->route('manage.suppliers.index')->with('success', 'Supplier added.');
    }

    /**
     * Read-only summary: this supplier's billing details plus every
     * "transaction" billed through them in a date range - defaults to the
     * past 3 months, same date-range-picker pattern as the Manage -> Work
     * Sessions review list. Two different kinds of transaction feed this,
     * since a supplier can be a materials/hire supplier, a "labour
     * supplier" a worker bills through (see Role::supplier()), or both:
     * Expenses logged directly against the supplier, and work sessions
     * belonging to any worker currently linked to it.
     */
    public function show(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->property_id === (int) session('current_property_id'), 404);

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $expenses = $supplier->expenses()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('farmJob')
            ->get()
            ->map(fn ($expense) => [
                'type' => 'expense',
                'id' => $expense->id,
                'date' => $expense->created_at,
                'name' => $expense->name,
                'description' => $expense->description,
                'amount' => $expense->amount !== null ? (float) $expense->amount : null,
                'gst_inclusive' => $expense->gst_inclusive,
                'reimburse' => $expense->reimburse,
                'farm_job' => $expense->farmJob ? ['id' => $expense->farmJob->id, 'name' => $expense->farmJob->name] : null,
            ]);

        $linkedUserIds = Role::where('property_id', $supplier->property_id)
            ->where('supplier_id', $supplier->id)
            ->pluck('user_id');

        $workSessions = WorkSession::where('property_id', $supplier->property_id)
            ->whereIn('user_id', $linkedUserIds)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with(['user', 'farmJob'])
            ->get()
            ->map(fn ($session) => [
                'type' => 'labour',
                'id' => $session->id,
                'date' => $session->started_at,
                'name' => $session->user->name,
                'status' => $session->status,
                'duration_in_hours' => $session->duration_in_hours,
                'amount' => $session->billing_amount ? (float) $session->billing_amount : null,
                'farm_job' => $session->farmJob ? ['id' => $session->farmJob->id, 'name' => $session->farmJob->name] : null,
            ]);

        $transactions = $expenses->concat($workSessions)->sortByDesc('date')->values();

        return Inertia::render('Manage/Suppliers/Show', [
            'supplier' => $supplier,
            'transactions' => $transactions,
            'total' => round($transactions->sum('amount'), 2),
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        abort_unless($supplier->property_id === (int) session('current_property_id'), 404);

        return Inertia::render('Manage/Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->property_id === (int) session('current_property_id'), 404);

        $supplier->update($this->validated($request));

        return redirect()->route('manage.suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        abort_unless($supplier->property_id === (int) session('current_property_id'), 404);

        $supplier->delete();

        return redirect()->route('manage.suppliers.index')->with('success', 'Supplier deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'billing_company_name' => 'nullable|string|max:255',
            'billing_abn' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string|max:255',
            'billing_phone' => 'nullable|string|max:255',
            'billing_contact_name' => 'nullable|string|max:255',
        ]);
    }

    private function parseDateRange(Request $request): array
    {
        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->subMonths(3)->startOfDay();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        return [$dateFrom, $dateTo];
    }
}
