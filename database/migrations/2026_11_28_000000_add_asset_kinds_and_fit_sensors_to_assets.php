<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The new kinds, each with the names an existing type of it might
    // already have (in lower case), and the name of a new one.
    private const KINDS = [
        'gate' => [['gate', 'gates'], 'Gate'],
        'tank' => [['tank', 'tanks', 'water tank', 'water tanks'], 'Water tank'],
        'pump' => [['pump', 'pumps'], 'Pump'],
        'vegetation' => [['vegetation', 'trees', 'tree'], 'Vegetation'],
    ];

    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // The asset the sensor is fitted to, e.g. a tank's level sensor.
            // The asset stays if the sensor is removed, and the other way round.
            $table->foreignId('asset_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
        });

        // Every property gets a type of each kind: its own if it has one by
        // that name already, otherwise a new one.
        foreach (DB::table('properties')->pluck('id') as $propertyId) {
            foreach (self::KINDS as $kind => [$names, $name]) {
                if (DB::table('asset_types')->where('property_id', $propertyId)->where('kind', $kind)->exists()) {
                    continue;
                }
                $existing = DB::table('asset_types')
                    ->where('property_id', $propertyId)
                    ->whereNull('kind')
                    ->whereIn(DB::raw('lower(trim(name))'), $names)
                    ->orderBy('id')
                    ->value('id');

                if ($existing) {
                    DB::table('asset_types')->where('id', $existing)->update(['kind' => $kind]);
                } else {
                    DB::table('asset_types')->insert([
                        'property_id' => $propertyId,
                        'name' => $name,
                        'kind' => $kind,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
        // The types stay, as assets may be in them - just no longer special.
        DB::table('asset_types')->whereIn('kind', array_keys(self::KINDS))->update(['kind' => null]);
    }
};
