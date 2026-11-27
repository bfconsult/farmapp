<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asset types get two levels: categories (Fixed, Plant, Stock...) and
     * sub-types under them. The fence, gate, tank, pump, trough and
     * vegetation types go under each property's Fixed category - made if
     * it has none.
     */
    public function up(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('property_id')->constrained('asset_types')->nullOnDelete();
        });

        foreach (DB::table('properties')->pluck('id') as $propertyId) {
            $kinded = DB::table('asset_types')->where('property_id', $propertyId)->whereNotNull('kind')->whereNull('parent_id');
            if (! $kinded->exists()) {
                continue;
            }

            $fixed = DB::table('asset_types')
                ->where('property_id', $propertyId)
                ->whereNull('kind')
                ->whereIn(DB::raw('lower(trim(name))'), ['fixed', 'fixed assets'])
                ->orderBy('id')
                ->value('id');

            $fixed ??= DB::table('asset_types')->insertGetId([
                'property_id' => $propertyId,
                'name' => 'Fixed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kinded->update(['parent_id' => $fixed]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
