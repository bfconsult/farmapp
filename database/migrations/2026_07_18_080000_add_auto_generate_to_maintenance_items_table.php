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
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->boolean('auto_generate')->default(false)->after('repeat_period_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropColumn('auto_generate');
        });
    }
};
