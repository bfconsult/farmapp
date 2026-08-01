<?php

use App\Models\Expense;
use App\Models\Property;
use App\Models\Supplier;
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
        if (!Schema::hasColumn('suppliers', 'property_id')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // Unlike priorities/job_types/asset_types/job_statuses (generic
        // template lists every property should equally start with),
        // suppliers are real per-property business contacts - duplicating
        // one property's supplier into every other property would pollute
        // everyone's list with irrelevant contacts. Instead, only give each
        // supplier a property-scoped copy for the specific property (or
        // properties) whose expenses actually reference it, via
        // Expense.farm_job_id -> FarmJob.property_id (Supplier has no
        // direct property_id today). A supplier no expense ever referenced
        // is dropped outright - nothing depends on it.
        Supplier::whereNull('property_id')->get()->each(function (Supplier $old) {
            $propertyIds = Expense::where('supplier_id', $old->id)
                ->with('farmJob')
                ->get()
                ->pluck('farmJob.property_id')
                ->filter()
                ->unique();

            foreach ($propertyIds as $propertyId) {
                $copy = Supplier::create([
                    'property_id' => $propertyId,
                    'name' => $old->name,
                    'description' => $old->description,
                    'street_address' => $old->street_address,
                    'phone' => $old->phone,
                    'email' => $old->email,
                ]);

                Expense::where('supplier_id', $old->id)
                    ->whereHas('farmJob', fn ($query) => $query->where('property_id', $propertyId))
                    ->update(['supplier_id' => $copy->id]);
            }
        });

        Supplier::whereNull('property_id')->delete();

        if (Schema::hasColumn('suppliers', 'property_id')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
