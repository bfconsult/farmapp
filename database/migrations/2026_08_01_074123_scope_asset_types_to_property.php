<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Property;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('asset_types', 'property_id')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // asset_types was a single global list shared by every property -
        // give each existing property its own copy of the current global
        // rows (verbatim), then repoint every Asset at its own property's
        // copy instead of the old shared row.
        $globalAssetTypes = AssetType::whereNull('property_id')->get();
        $oldIdToName = $globalAssetTypes->pluck('name', 'id');

        Property::all()->each(function (Property $property) use ($globalAssetTypes, $oldIdToName) {
            $newIdByName = AssetType::where('property_id', $property->id)->pluck('id', 'name')->all();

            if (empty($newIdByName)) {
                foreach ($globalAssetTypes as $old) {
                    $copy = AssetType::create([
                        'property_id' => $property->id,
                        'name' => $old->name,
                    ]);
                    $newIdByName[$old->name] = $copy->id;
                }
            }

            Asset::where('property_id', $property->id)
                ->whereNotNull('asset_type_id')
                ->get()
                ->each(function (Asset $asset) use ($newIdByName, $oldIdToName) {
                    $name = $oldIdToName[$asset->asset_type_id] ?? null;
                    if ($name && isset($newIdByName[$name]) && $asset->asset_type_id !== $newIdByName[$name]) {
                        $asset->update(['asset_type_id' => $newIdByName[$name]]);
                    }
                });
        });

        AssetType::whereNull('property_id')->delete();

        if (Schema::hasColumn('asset_types', 'property_id')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
