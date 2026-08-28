<?php

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
        Schema::create('livestock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_type_id')->nullable()->constrained('livestock_types')->nullOnDelete();
            $table->foreignId('mob_id')->nullable()->constrained('mobs')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('tag_number');
            $table->string('name')->nullable();
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            // Separate from date_of_birth - many animals are bought in
            // rather than bred on the property, so the two dates can both
            // be set, either alone, or neither.
            $table->date('purchase_date')->nullable();
            // Birth weight is rarely known even for animals bred on the
            // property, hence nullable with no other constraint - unlike
            // purchase/sale weight it has no paired price.
            $table->decimal('birth_weight', 8, 2)->nullable();
            $table->decimal('purchase_weight', 8, 2)->nullable();
            // Bought/sold either per kilo of live weight or as a flat
            // per-head price - the type governs how purchase_price/
            // sale_price should be read, same pairing on both ends.
            $table->enum('purchase_price_type', ['per_kg', 'per_unit'])->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('sale_weight', 8, 2)->nullable();
            $table->enum('sale_price_type', ['per_kg', 'per_unit'])->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            // Not user-configurable (unlike LivestockType) - a fixed
            // lifecycle, same pattern as work_sessions.status. Once an
            // animal is sold/deceased/culled the record is kept (status
            // changed, not deleted) so parentage/offspring history survives.
            $table->enum('status', ['active', 'sold', 'deceased', 'culled'])->default('active');
            // Self-referential - nullOnDelete mirrors quotes.supplier_id:
            // a parent record being deleted shouldn't cascade-delete its
            // offspring's historical record.
            $table->foreignId('sire_id')->nullable()->constrained('livestock')->nullOnDelete();
            $table->foreignId('dam_id')->nullable()->constrained('livestock')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'tag_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestock');
    }
};
