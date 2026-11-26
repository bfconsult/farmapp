<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            // What the app treats an asset of this type as, whatever the
            // property calls the type - 'fence': drawn as a line, and shown
            // in the farm view.
            $table->string('kind', 20)->nullable()->after('name');
        });

        Schema::table('assets', function (Blueprint $table) {
            // Details for its type's kind, e.g. a fence's type and wires.
            $table->json('details')->nullable()->after('asset_type_id');
        });

        Schema::table('asset_locations', function (Blueprint $table) {
            // A run of [lat, lng] points - a fence, pipe or track - as well
            // as the existing point and (closed) shape.
            $table->json('line')->nullable()->after('shape');
        });

        // Every property gets a fence type: its own "Fence" type if it has
        // one already, otherwise a new one.
        foreach (DB::table('properties')->pluck('id') as $propertyId) {
            $existing = DB::table('asset_types')
                ->where('property_id', $propertyId)
                ->whereIn(DB::raw('lower(trim(name))'), ['fence', 'fences', 'fencing'])
                ->orderBy('id')
                ->value('id');

            if ($existing) {
                DB::table('asset_types')->where('id', $existing)->update(['kind' => 'fence']);
            } else {
                DB::table('asset_types')->insert([
                    'property_id' => $propertyId,
                    'name' => 'Fence',
                    'kind' => 'fence',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('asset_locations', function (Blueprint $table) {
            $table->dropColumn('line');
        });
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('details');
        });
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
