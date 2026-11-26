<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Which way round the farm view shows the property, in degrees
            // - its "right way up", which the map data can't tell.
            $table->unsignedSmallInteger('farm_view_rotation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('farm_view_rotation');
        });
    }
};
