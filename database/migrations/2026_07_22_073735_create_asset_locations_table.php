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
        Schema::create('asset_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            // Nullable, unlike created_by elsewhere (assets.created_by,
            // maintenance_items.created_by) - migrated legacy rows have no
            // true "who set this" fact, and shouldn't cascade-delete history
            // if that user is later removed.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Same types/nullability as the columns being replaced on
            // `assets`. At most one of latitude/longitude or shape is set
            // per row - still app-enforced only, not DB-enforced, same as
            // before. A row with all three null represents an explicit
            // "location cleared" entry, not "no data".
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('shape')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_locations');
    }
};
