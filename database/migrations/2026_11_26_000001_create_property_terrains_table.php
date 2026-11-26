<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A grid of ground heights over a property, for the farm view's hills
        // and contours. Its own table so loading a property doesn't load it.
        Schema::create('property_terrains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('north', 10, 7);
            $table->decimal('south', 10, 7);
            $table->decimal('west', 10, 7);
            $table->decimal('east', 10, 7);
            // Points across (west to east) and down (north to south).
            $table->unsignedSmallInteger('columns');
            $table->unsignedSmallInteger('rows');
            // Metres above sea level, row by row from the north-west corner.
            $table->json('heights');
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_terrains');
    }
};
