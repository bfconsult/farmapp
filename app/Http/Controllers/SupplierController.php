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
}
