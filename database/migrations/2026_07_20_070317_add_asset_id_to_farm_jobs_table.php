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
        Schema::table('farm_jobs', function (Blueprint $table) {
            // Direct link for ad-hoc asset work that isn't from a maintenance
            // schedule - see Asset::jobs(), which combines this with jobs
            // reached via maintenance_item_id.
            $table->foreignId('asset_id')->nullable()->after('maintenance_item_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
