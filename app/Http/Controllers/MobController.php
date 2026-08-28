<?php

namespace App\Http\Controllers;

use App\Models\Livestock;
use App\Models\Mob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MobController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where('property_id', session('current_property_id'))],
        ]);

        $mob = Mob::create([
            'property_id' => session('current_property_id'),
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
        ]);

        if (filled($validated['zone_id'] ?? null)) {
            $mob->zoneHistory()->create([
                'zone_id' => $validated['zone_id'],
                'created_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Mob created.');
    }

    public function update(Request $request, Mob $mob)
    {
        abort_unless($mob->property_id === (int) session('current_property_id'), 404);

        $mob->update($request->validate([
            'name' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Mob updated.');
    }

    /**
     * Records a new current paddock for this mob - every call inserts a
     * fresh MobZoneHistory row (including "take off any paddock", which
     * inserts a null-zone row) rather than overwriting one, so paddock
     * history accumulates. Mirrors AssetController::updateLocation().
     */
    public function updateZone(Request $request, Mob $mob)
    {
        abort_unless($mob->property_id === (int) session('current_property_id'), 404);

        $validated = $request->validate([
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where('property_id', session('current_property_id'))],
        ]);

        $mob->zoneHistory()->create([
            'zone_id' => $validated['zone_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Paddock updated.');
    }

    /**
     * Bulk-creates animals into this mob from a single starting tag number,
     * for the common case of populating a freshly-created mob in one go.
     * Every field but tag_number is copied unchanged onto each animal;
     * tag_number is auto-incremented from the given starting value.
     */
    public function storeAnimals(Request $request, Mob $mob)
    {
        abort_unless($mob->property_id === (int) session('current_property_id'), 404);

        $currentPropertyId = $mob->property_id;

        $validated = $request->validate([
            'tag_number' => 'required|string|max:255',
            'count' => 'required|integer|min:1|max:100',
            'sex' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'birth_weight' => 'nullable|numeric|min:0',
            'purchase_weight' => 'nullable|numeric|min:0',
            'purchase_price_type' => 'nullable|in:per_kg,per_unit',
            'purchase_price' => 'nullable|numeric|min:0',
            'livestock_type_id' => ['nullable', Rule::exists('livestock_types', 'id')->where('property_id', $currentPropertyId)],
        ]);

        $tags = $this->tagSequence($validated['tag_number'], (int) $validated['count']);

        $taken = Livestock::where('property_id', $currentPropertyId)
            ->whereIn('tag_number', $tags)
            ->pluck('tag_number');

        if ($taken->isNotEmpty()) {
            return back()->withErrors([
                'tag_number' => 'Already in use: '.$taken->implode(', '),
            ]);
        }

        DB::transaction(function () use ($tags, $validated, $mob, $currentPropertyId, $request) {
            foreach ($tags as $tag) {
                Livestock::create([
                    'property_id' => $currentPropertyId,
                    'mob_id' => $mob->id,
                    'created_by' => $request->user()->id,
                    'tag_number' => $tag,
                    'sex' => $validated['sex'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'birth_weight' => $validated['birth_weight'] ?? null,
                    'purchase_weight' => $validated['purchase_weight'] ?? null,
                    'purchase_price_type' => $validated['purchase_price_type'] ?? null,
                    'purchase_price' => $validated['purchase_price'] ?? null,
                    'livestock_type_id' => $validated['livestock_type_id'] ?? null,
                ]);
            }
        });

        return back()->with('success', count($tags).' animals added.');
    }

    /**
     * Expands a starting tag into $count sequential tags by incrementing its
     * trailing numeric run (e.g. "NLIS001" -> 001, 002, 003...), preserving
     * zero-padding width. A tag with no trailing digits can't be numerically
     * incremented, so it's suffixed "-2", "-3"... instead.
     */
    private function tagSequence(string $startingTag, int $count): array
    {
        if (! preg_match('/^(.*?)(\d+)$/', $startingTag, $matches)) {
            $tags = [$startingTag];
            for ($i = 2; $i <= $count; $i++) {
                $tags[] = "{$startingTag}-{$i}";
            }

            return $tags;
        }

        [, $prefix, $number] = $matches;
        $width = strlen($number);
        $start = (int) $number;

        return array_map(
            fn ($offset) => $prefix.str_pad((string) ($start + $offset), $width, '0', STR_PAD_LEFT),
            range(0, $count - 1)
        );
    }

    public function destroy(Mob $mob)
    {
        abort_unless($mob->property_id === (int) session('current_property_id'), 404);

        $mob->delete();

        return back()->with('success', 'Mob deleted.');
    }

    public function show(Mob $mob)
    {
        abort_unless($mob->property_id === (int) session('current_property_id'), 404);

        $mob->load([
            'currentZone.zone',
            'zoneHistory.zone',
            'livestock.livestockType',
            'property.zones',
            'notes.photos',
            'notes.createdBy',
            'notes.views',
        ]);
        $mob->notes->each(fn ($note) => $note->is_unread = $note->isUnreadBy(Auth::id()));

        return Inertia::render('Mobs/Show', [
            'mob' => $mob,
        ]);
    }
}
