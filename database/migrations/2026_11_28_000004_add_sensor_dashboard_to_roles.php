<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each person's own arrangement of the sensor dashboard on a property:
     * [{i, x, y, w, h}] per tile. Kept on their role there - it's theirs on
     * that property, and goes when they leave it (or are deleted) without
     * anything else depending on it.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('sensor_dashboard')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('sensor_dashboard');
        });
    }
};
