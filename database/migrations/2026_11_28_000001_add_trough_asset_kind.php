<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Names an existing trough type might already have, in lower case.
    private const NAMES = ['trough', 'troughs', 'water trough', 'water troughs', 'stock trough', 'stock troughs'];

    /**
     * Every property gets a trough type: its own if it has one by that name
     * already, otherwise a new one.
     */
    public function up(): void
    {
        foreach (DB::table('properties')->pluck('id') as $propertyId) {
            if (DB::table('asset_types')->where('property_id', $propertyId)->where('kind', 'trough')->exists()) {
                continue;
            }
            $existing = DB::table('asset_types')
                ->where('property_id', $propertyId)
                ->whereNull('kind')
                ->whereIn(DB::raw('lower(trim(name))'), self::NAMES)
                ->orderBy('id')
                ->value('id');

            if ($existing) {
                DB::table('asset_types')->where('id', $existing)->update(['kind' => 'trough']);
            } else {
                DB::table('asset_types')->insert([
                    'property_id' => $propertyId,
                    'name' => 'Trough',
                    'kind' => 'trough',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // The types stay, as assets may be in them - just no longer special.
        DB::table('asset_types')->where('kind', 'trough')->update(['kind' => null]);
    }
};
