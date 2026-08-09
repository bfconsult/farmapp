<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
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
     * Read-only summary: this supplier's billing details plus every expense
     * (job "transaction") logged against them in a date range - defaults to
     * the past 3 months, same date-range-picker pattern as the Manage ->
     * Work Sessions review list.
     */
    public function show(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->property_id === (int) session('current_property_id'), 404);

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $expenses = $supplier->expenses()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('farmJob')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Manage/Suppliers/Show', [
            'supplier' => $supplier,
            'expenses' => $expenses,
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
