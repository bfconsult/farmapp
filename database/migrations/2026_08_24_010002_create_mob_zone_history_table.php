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
        Schema::create('mob_zone_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mob_id')->constrained()->cascadeOnDelete();
            // Nullable - a mob can be moved off any paddock, which is itself
            // a recorded event, not just an absence of rows. Also nullable
            // so a later-deleted zone doesn't erase the history entry.
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            // Nullable, same reasoning as asset_locations.created_by -
            // history shouldn't disappear (or cascade-delete) if the user
            // who recorded it is later removed.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mob_zone_history');
    }
};
