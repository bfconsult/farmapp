<?php

namespace App\Http\Controllers;

use App\Models\Livestock;
use App\Models\LivestockType;
use App\Models\Mob;
use App\Models\Property;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LivestockController extends Controller
{
    public function index()
    {
        $currentPropertyId = session('current_property_id');
        $currentProperty = $currentPropertyId ? Property::find($currentPropertyId) : null;
        $canManage = in_array(Auth::user()->roleOn($currentProperty), ['admin', 'manager'], true);

        // Visible to every role - only the CRUD controls on top of this are
        // canManage-gated.
        $mobs = Mob::where('property_id', $currentPropertyId)
            ->withCount('livestock')
            ->with('currentZone.zone')
            ->orderBy('name')
            ->get();

        $livestock = Livestock::where('property_id', $currentPropertyId)
            ->with(['livestockType', 'mob'])
            ->orderBy('tag_number')
            ->get();

        return Inertia::render('Manage/Livestock', [
            'mobs' => $mobs,
            'livestock' => $livestock,
            'livestockTypes' => $canManage ? LivestockType::where('property_id', $currentPropertyId)->orderBy('name')->get() : [],
            'zones' => $canManage ? Zone::where('property_id', $currentPropertyId)->orderBy('name')->get() : [],
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Livestock::create([
            ...$validated,
            'property_id' => session('current_property_id'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Animal added.');
    }

    public function update(Request $request, Livestock $livestock)
    {
        abort_unless($livestock->property_id === (int) session('current_property_id'), 404);

        $livestock->update($this->validated($request, $livestock));

        return back()->with('success', 'Animal updated.');
    }

    public function destroy(Livestock $livestock)
    {
        abort_unless($livestock->property_id === (int) session('current_property_id'), 404);

        $livestock->delete();

        return back()->with('success', 'Animal deleted.');
    }

    public function show(Livestock $livestock)
    {
        abort_unless($livestock->property_id === (int) session('current_property_id'), 404);

        $livestock->load(['livestockType', 'mob', 'sire', 'dam', 'notes.photos', 'notes.createdBy', 'notes.views']);
        $livestock->notes->each(fn ($note) => $note->is_unread = $note->isUnreadBy(Auth::id()));

        $currentPropertyId = $livestock->property_id;

        // Same-property, same-species (when a species is set) candidates for
        // the sire/dam picker - never the animal itself.
        $potentialParents = Livestock::where('property_id', $currentPropertyId)
            ->where('id', '!=', $livestock->id)
            ->when($livestock->livestock_type_id, fn ($query) => $query->where('livestock_type_id', $livestock->livestock_type_id))
            ->orderBy('tag_number')
            ->get(['id', 'tag_number', 'name']);

        return Inertia::render('Livestock/Show', [
            'livestock' => $livestock,
            'offspring' => $livestock->offspring()->orderBy('tag_number')->get(),
            'potentialParents' => $potentialParents,
            'mobs' => Mob::where('property_id', $currentPropertyId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request, ?Livestock $livestock = null): array
    {
        $currentPropertyId = session('current_property_id');

        return $request->validate([
            'tag_number' => [
                'required', 'string', 'max:255',
                Rule::unique('livestock', 'tag_number')->where('property_id', $currentPropertyId)->ignore($livestock?->id),
            ],
            'name' => 'nullable|string|max:255',
            'sex' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'birth_weight' => 'nullable|numeric|min:0',
            'purchase_weight' => 'nullable|numeric|min:0',
            'purchase_price_type' => 'nullable|in:per_kg,per_unit',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_weight' => 'nullable|numeric|min:0',
            'sale_price_type' => 'nullable|in:per_kg,per_unit',
            'sale_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,sold,deceased,culled',
            'livestock_type_id' => ['nullable', Rule::exists('livestock_types', 'id')->where('property_id', $currentPropertyId)],
            'mob_id' => ['nullable', Rule::exists('mobs', 'id')->where('property_id', $currentPropertyId)],
            'sire_id' => [
                'nullable',
                Rule::exists('livestock', 'id')->where('property_id', $currentPropertyId),
                function ($attribute, $value, $fail) use ($livestock) {
                    if ($livestock && (int) $value === $livestock->id) {
                        $fail('An animal cannot be its own sire.');
                    }
                },
            ],
            'dam_id' => [
                'nullable',
                Rule::exists('livestock', 'id')->where('property_id', $currentPropertyId),
                function ($attribute, $value, $fail) use ($livestock) {
                    if ($livestock && (int) $value === $livestock->id) {
                        $fail('An animal cannot be its own dam.');
                    }
                },
            ],
        ]);
    }
}
