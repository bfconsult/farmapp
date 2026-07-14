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
        Schema::table('properties', function (Blueprint $table) {
            // An optional circle (the on-site residence) within the property
            // boundary - splits a resident worker's day into discrete work
            // sessions instead of one continuous multi-day visit. Plain
            // columns rather than a related table (unlike `shapes`, which
            // holds an arbitrary-length polygon) since a circle is just
            // these three fixed scalar values.
            $table->decimal('non_working_zone_center_lat', 10, 7)->nullable();
            $table->decimal('non_working_zone_center_lng', 10, 7)->nullable();
            $table->unsignedInteger('non_working_zone_radius_meters')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'non_working_zone_center_lat',
                'non_working_zone_center_lng',
                'non_working_zone_radius_meters',
            ]);
        });
    }
};
